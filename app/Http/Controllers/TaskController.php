<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreBulkTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\DefaultTask;
use App\Models\ExternalTask;
use App\Models\Location;
use App\Models\Requirement;
use App\Models\Task;
use App\Services\ImageService;
use App\Services\PlanningTaskRejectionService;
use App\Services\PlanningTaskSyncService;
use App\Services\RecurringTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource for a specific location.
     * De 'index' route is vaak /locations/{location}/tasks
     */
    public function index(Request $request, Location $location): View|JsonResponse
    {
        $query = $location->tasks();

        // Exclude recurring tasks by default to keep the list focused on one-off/location-specific tasks
        $query->where('is_recurring', false);

        // If the authenticated user is in customer service, restrict to concept tasks
        if (auth()->check() && auth()->user()->role === Role::CUSTOMER_SERVICE) {
            $query->where('status', 'concept');
        }

        // Read query parameters explicitly to preserve exactly what user provided in the URL
        $searchTerm = $request->query('search_term');
        $activeFilter = $request->has('filter')
            ? $request->query('filter')
            : 'open';
        $plannedFilter = $request->query('planned_filter');

        // Valid sortable columns for tasks
        $sortableColumns = ['title', 'priority', 'status', 'deadline', 'estimated_hours', 'created_at'];

        $sortByInput = $request->input('sort_by');
        $sortDirectionInput = $request->input('sort_direction');

        if (! $sortByInput) {
            // DEFAULT SORTING (no sort parameters in URL)
            $query->orderByRaw('deadline IS NULL ASC, deadline ASC') // Tasks with deadlines first (earliest), then tasks without deadlines
                ->orderByRaw('CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC', [
                    TaskPriority::HIGH->value,
                    TaskPriority::NORMAL->value,
                    TaskPriority::LOW->value,
                ]) // Priority ASC (High > Normal > Low)
                ->orderBy('created_at', 'desc'); // Created_at DESC (Newest first)

            // Set $sortBy and $sortDirection for the view to reflect the conceptual default primary sort
            $sortBy = 'deadline'; // Default view state reflects deadline as primary of the set
            $sortDirection = 'asc'; // Default direction for deadline is now ASC
        } else {
            // USER SPECIFIED SORTING
            $sortBy = $sortByInput;
            if (! in_array($sortBy, $sortableColumns)) {
                $sortBy = 'created_at'; // Fallback if invalid column
                $sortDirection = 'desc';
            } else {
                $sortDirection = strtolower($sortDirectionInput) === 'desc' ? 'desc' : 'asc';
            }

            // Apply primary user-defined sort
            if ($sortBy === 'priority') {
                $query->orderByRaw(
                    'CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END '.$sortDirection,
                    [TaskPriority::HIGH->value, TaskPriority::NORMAL->value, TaskPriority::LOW->value]
                );
            } elseif ($sortBy === 'deadline') {
                if ($sortDirection === 'asc') {
                    $query->orderByRaw('(deadline IS NULL) ASC, deadline ASC'); // NULLs last
                } else { // desc
                    $query->orderByRaw('(deadline IS NULL) ASC, deadline DESC'); // NULLs last, then by deadline DESC
                }
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Consistent tie-breakers for user-defined sorts
            if ($sortBy !== 'created_at' && $sortBy !== 'deadline') { // Avoid re-adding if primary or part of deadline's complex sort
                $query->orderByRaw('(deadline IS NULL) ASC, deadline ASC');
            }
            if ($sortBy !== 'priority') {
                $query->orderByRaw('CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC', [
                    TaskPriority::HIGH->value, TaskPriority::NORMAL->value, TaskPriority::LOW->value,
                ]);
            }
            if ($sortBy !== 'created_at') {
                $query->orderBy('created_at', 'desc');
            }
            // Add an ultimate tie-breaker for absolute consistency if needed, e.g., by ID
            // $query->orderBy('id', 'asc');
        }

        // Search functionality
        if (! empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter functionality
        if ($activeFilter && $activeFilter !== 'all') {
            match ($activeFilter) {
                'open' => $query->whereNotIn('status', ['completed', 'rejected']),
                'completed' => $query->where('status', '=', 'completed'),
                'priority_high' => $query->where('priority', TaskPriority::HIGH),
                'priority_normal' => $query->where('priority', TaskPriority::NORMAL),
                'priority_low' => $query->where('priority', TaskPriority::LOW),
                default => null,
            };
        }

        // Filter by planned status
        if ($plannedFilter) {
            match ($plannedFilter) {
                'planned' => $query->whereHas('planningTasks'),
                'unplanned' => $query->whereDoesntHave('planningTasks'),
                default => null, // 'all' or any other value will not apply this specific filter
            };
        }

        if ($request->wantsJson()) {
            return response()->json([
                'tasks' => $query->get()->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => [
                            'value' => $task->priority->value,
                            'label' => $task->priority->label(),
                        ],
                        'status' => $task->status ?? TaskStatus::OPEN,
                        'deadline' => $task->deadline,
                        'estimated_time_minutes' => $task->estimated_time_minutes ?? 0,
                        'location_id' => $task->location_id,
                    ];
                }),
            ]);
        }

        // Eager load relationships before pagination (paginator itself does not support load())
        $query->with(['planningTasks.planning']);

        $perPage = $this->resolvePerPage($request, $query);
        $tasks = $query->paginate($perPage);
        // Ensure pagination URLs preserve the current view state (even when defaults are used)
        $appendParams = [
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
            'filter' => $activeFilter,
            'planned_filter' => $plannedFilter,
        ];
        $perPageParam = $request->query('per_page');
        if ($perPageParam !== null) {
            $appendParams['per_page'] = $perPageParam;
        }
        if ($searchTerm !== null && $searchTerm !== '') {
            $appendParams['search_term'] = $searchTerm;
        }
        $tasks->appends($appendParams);

        // Relationships already eager-loaded on the query above

        return view($this->viewName('tasks.index'), compact(
            'location',
            'tasks',
            'sortBy',
            'sortDirection',
            'searchTerm',
            'activeFilter',
            'plannedFilter'
        ));
    }

    /**
     * Show a page to select a location before creating a new task.
     */
    public function selectLocationForTask(Request $request): View
    {
        $searchTerm = $request->input('search_term', '');

        $locationsQuery = Location::query()->withCount([
            'tasks as tasks_count' => function ($query) {
                $query->whereNotIn('status', [
                    TaskStatus::COMPLETED->value,
                    TaskStatus::REJECTED->value,
                ]);
            },
        ]);

        if (! empty($searchTerm)) {
            $locationsQuery->whereRaw('LOWER(name) LIKE ?', [strtolower("%{$searchTerm}%")]);
        }

        $locations = $locationsQuery->orderBy('name')->get();

        return view($this->viewName('tasks.select-location'), compact('locations', 'searchTerm'));
    }

    /**
     * Show form for bulk creating tasks.
     */
    public function bulkCreate(): View
    {
        $locations = Location::orderBy('name')->get();
        $requirements = Requirement::orderBy('name')->get();
        $availableDoorTypes = DefaultTask::getAvailableDoorTypes();

        return view($this->viewName('tasks.bulk-create'), compact('locations', 'requirements', 'availableDoorTypes'));
    }

    /**
     * Store bulk created tasks.
     */
    public function bulkStore(StoreBulkTaskRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();
        $validatedData['created_by'] = auth()->id();

        // If the creator is a customer_service user, default the status to concept
        if (auth()->check() && auth()->user()->role === Role::CUSTOMER_SERVICE) {
            $validatedData['status'] = TaskStatus::CONCEPT;
        }

        $validatedData['is_photo_required'] = $request->has('is_photo_required');

        // Determine locations
        $locationIds = [];
        if ($request->boolean('applies_to_all_locations')) {
            $locationIds = Location::pluck('id')->toArray();
        } elseif ($request->boolean('applies_to_lift_locations')) {
            $locationIds = Location::where('lift', 'Ja')->pluck('id')->toArray();
        } elseif ($request->boolean('applies_to_door_types') && ! empty($validatedData['door_types'])) {
            $doorTypes = array_map('trim', array_map('strtolower', $validatedData['door_types']));
            $locationIds = Location::whereIn(DB::raw('TRIM(LOWER(type_deur))'), $doorTypes)->pluck('id')->toArray();
        } elseif (! empty($validatedData['locations'])) {
            $locationIds = $validatedData['locations'];
        }

        if (empty($locationIds)) {
            return redirect()->back()->withInput()->with('error', 'Geen locaties geselecteerd of gevonden voor de opgegeven criteria.');
        }

        $createdCount = 0;
        foreach ($locationIds as $locationId) {
            $taskData = $validatedData;
            $taskData['location_id'] = $locationId;

            $task = Task::create($taskData);

            // Sync requirements
            if (! empty($validatedData['requirements'])) {
                $task->requirements()->sync($validatedData['requirements']);
            }

            // Note: Photos are not supported for bulk create for now as it would complicate storage
            // and might not be what's expected (same photo for all locations?).
            // If needed, we could implement it, but standard tasks also don't usually have photos on creation.

            $createdCount++;
        }

        return redirect()->route('backlog.index')->with('success', "{$createdCount} taken succesvol aangemaakt.");
    }

    /**
     * Show the form for creating a new resource for a specific location.
     * De 'create' route is vaak /locations/{location}/tasks/create
     */
    public function create(Request $request, Location $location): View
    {
        $requirements = Requirement::orderBy('name')->get();

        // Get prefilled data from session (if redirected from rejected checklist item)
        $prefill = session('prefill', []);

        return view($this->viewName('tasks.create'), compact('location', 'requirements', 'prefill'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request, Location $location): RedirectResponse|JsonResponse
    {
        // De StoreTaskRequest zou al gevalideerd moeten hebben dat location_id overeenkomt
        // of de location_id uit de route parameter moeten gebruiken.
        $validatedData = $request->validated();
        $validatedData['created_by'] = auth()->id();

        // If the creator is a customer_service user, default the status to concept
        if (auth()->check() && auth()->user()->role === Role::CUSTOMER_SERVICE) {
            $validatedData['status'] = TaskStatus::CONCEPT;
        }

        $validatedData['is_photo_required'] = $request->has('is_photo_required');

        $new_task = $location->tasks()->create($validatedData); // Assign to variable to use in message if needed

        // Sync requirements
        if (! empty($validatedData['requirements'])) {
            $new_task->requirements()->sync($validatedData['requirements']);
        }

        if (! empty($validatedData['benodigdheden'])) {
            $new_task->requirements()->sync($validatedData['benodigdheden']);
        }

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            $imageService = app(ImageService::class);

            foreach ($request->file('photos') as $file) {
                $filename = uniqid('tp_'.$new_task->id.'_', true).'.'.$file->getClientOriginalExtension();

                try {
                    $path = $imageService->saveCompressedImage(
                        $file,
                        'task-photos/'.$new_task->id,
                        $filename,
                        'public'
                    );

                    $new_task->taskPhotos()->create([
                        'file_path' => $path,
                        'uploaded_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Log the error but don't fail the task creation
                    Log::error('Error uploading task photo: '.$e->getMessage());
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Taak \"{$new_task->title}\" succesvol aangemaakt.",
                'task' => [
                    'id' => $new_task->id,
                    'title' => $new_task->title,
                    'description' => $new_task->description,
                    'priority' => [
                        'value' => $new_task->priority->value,
                        'label' => $new_task->priority->label(),
                    ],
                    'status' => $new_task->status ?? TaskStatus::OPEN,
                    'deadline' => $new_task->deadline,
                    'estimated_time_minutes' => $new_task->estimated_time_minutes ?? 0,
                    'location_id' => $new_task->location_id,
                ],
            ]);
        }

        // Redirect to the main backlog page with a success message.
        return redirect()->route('backlog.index')->with('success', "Taak \"{$new_task->title}\" succesvol aangemaakt en toegevoegd aan de backlog.");
    }

    /**
     * Display the specified resource.
     * Door 'shallow nesting' is de route vaak /tasks/{task}
     */
    public function show(Request $request, Task $task): View
    {
        // Load the main task with its planning tasks, and for each planning task,
        // load its completions with all necessary nested relationships.
        $task->load([
            'location',
            'taskPhotos',
            'creator',
            'planningTasks.completions' => function ($query) {
                $query->with(['user', 'photos', 'reviewer'])->orderBy('created_at', 'desc');
            },
        ]);

        // Now, collect all completions from all planning tasks into a single, sorted collection.
        $completion_history = $task->planningTasks
            ->flatMap(fn ($planningTask) => $planningTask->completions)
            ->sortByDesc('created_at');

        return view($this->viewName('tasks.show'), [
            'task' => $task,
            'completion_history' => $completion_history,
            'planning_id' => $request->get('planning'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * Door 'shallow nesting' is de route vaak /tasks/{task}/edit
     */
    public function edit(Task $task): View
    {
        $task->load(['location', 'requirements']); // Nodig voor context in de view, bv. broodkruimels
        $requirements = Requirement::orderBy('name')->get();
        $selectedRequirements = $task->requirements->pluck('id')->toArray();

        return view($this->viewName('tasks.edit'), compact('task', 'requirements', 'selectedRequirements'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse|JsonResponse
    {
        $validatedData = $request->validated();

        $validatedData['is_photo_required'] = $request->has('is_photo_required');

        // Set default status to 'open' if no status is provided
        if (! isset($validatedData['status']) || empty($validatedData['status'])) {
            $validatedData['status'] = 'open';
        }

        $task->update($validatedData);

        // Sync requirements
        $task->requirements()->sync($validatedData['requirements'] ?? []);

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            $imageService = app(ImageService::class);

            foreach ($request->file('photos') as $file) {
                $filename = uniqid('tp_'.$task->id.'_', true).'.'.$file->getClientOriginalExtension();

                try {
                    $path = $imageService->saveCompressedImage(
                        $file,
                        'task-photos/'.$task->id,
                        $filename,
                        'public'
                    );

                    $task->taskPhotos()->create([
                        'file_path' => $path,
                        'uploaded_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Log the error but don't fail the task update
                    Log::error('Error uploading task photo: '.$e->getMessage());
                }
            }
        }

        // Return JSON response for AJAX requests, redirect for normal requests
        if ($request->expectsJson()) {
            return response()->json([
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority->value,
                'status' => $task->status->value,
                'deadline' => $task->deadline,
                'estimated_time_minutes' => $task->estimated_time_minutes,
                'message' => 'Taak succesvol bijgewerkt.',
            ]);
        }

        // Redirect naar de taak show pagina, of de taken index van de locatie.
        return redirect()->route('tasks.show', $task)->with('success', 'Taak succesvol bijgewerkt.');
        // Alternatief: return redirect()->route('locations.tasks.index', $task->location_id)->with('success', 'Taak succesvol bijgewerkt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $location = $task->location; // Bewaar locatie voor redirect voordat taak verwijderd wordt
        $task->delete();

        return redirect()->route('locations.tasks.index', $location)->with('success', 'Taak succesvol verwijderd.');
        // Alternatief: return redirect()->route('locations.show', $location)->with('success', 'Taak succesvol verwijderd.');
    }

    public function approve(Request $request, Task $task, PlanningTaskSyncService $planningTaskSyncService): RedirectResponse
    {
        if ($task->status === TaskStatus::IN_REVIEW) {
            $task->update(['status' => TaskStatus::OPEN]);

            return redirect()->route('tasks.show', $task)->with('success', 'Taak goedgekeurd en status aangepast naar open.');
        }

        $task->update(['status' => TaskStatus::COMPLETED]);

        // Find the planning task that triggered the review and update its completion record
        $triggering_planning_task = $task->planningTasks()->where('status', TaskStatus::REVIEW)->latest('completed_at')->first();

        if ($triggering_planning_task) {
            // Update the planning task itself
            $triggering_planning_task->update(['status' => TaskStatus::COMPLETED]);

            // And update its latest completion attempt with the review details
            if ($latest_completion = $triggering_planning_task->completions()->latest()->first()) {
                $latest_completion->update([
                    'review_notes' => $request->input('review_notes'),
                    'reviewed_at' => now(),
                    'review_outcome' => 'approved',
                    'reviewed_by' => $request->user()->id,
                ]);
            }
        }

        // After approval, check if the parent planning is now fully completed
        if ($triggering_planning_task->planning != null) {
            $triggering_planning_task->planning->checkAndUpdateStatus();

            // Check if this location is now completed and notify if needed
            $planningTaskSyncService->checkLocationCompletionAndNotify($triggering_planning_task);
        }

        // Handle recurring task creation if applicable
        $recurringService = app(RecurringTaskService::class);
        $newRecurringTask = $recurringService->createRecurringInstance($task);

        $message = 'Taak goedgekeurd.';
        if ($newRecurringTask) {
            $intervalDescription = $task->getRecurringIntervalDescription();
            $message .= " Een nieuwe terugkerende taak is aangemaakt voor {$intervalDescription}.";
        }

        return redirect()->route('admin.tasks.review')->with('success', $message);
    }

    public function convertToExternal(Task $task): RedirectResponse
    {
        return DB::transaction(function () use ($task) {
            $externalTask = ExternalTask::create([
                'location_id' => $task->location_id,
                'title' => $task->title,
                'description' => $task->description,
                'feedback_information' => $task->feedback_information,
                'external_deadline_at' => $task->deadline,
                'estimated_time_minutes' => $task->estimated_time_minutes,
                'status' => TaskStatus::REVIEW,
                'priority' => $task->priority,
            ]);

            $task->delete();

            return redirect()->route('external-backlog.show', $externalTask)
                ->with('success', 'Taak is succesvol omgezet naar een externe taak.');
        });
    }

    public function reject(Request $request, Task $task, PlanningTaskRejectionService $planningTaskRejectionService): RedirectResponse
    {
        // Find the specific PlanningTask that is in review for this backlog task.
        $triggering_planning_task = $task->planningTasks()
            ->where('status', TaskStatus::REVIEW)
            ->latest('completed_at')
            ->firstOrFail(); // We must find one, otherwise we shouldn't be here.

        return $planningTaskRejectionService->reject($request, $triggering_planning_task);
    }
}
