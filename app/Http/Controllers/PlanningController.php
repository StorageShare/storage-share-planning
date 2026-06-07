<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Http\Requests\StorePlanningRequest;
use App\Http\Requests\UpdatePlanningRequest;
use App\Mail\PlanningReadyNotificationMail;
use App\Models\Location;
use App\Models\Planning;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleTask;
use App\Services\ExternalLocationService;
use App\Services\PlanningCompletionService;
use App\Services\PlanningFormDataService;
use App\Services\PlanningLocationSyncService;
use App\Services\PlanningLocationTimerService;
use App\Services\PlanningShowDataService;
use App\Services\PlanningTaskCreationService;
use App\Services\PlanningTaskUpdateService;
use App\Services\TravelTimeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
// For database transactions
use Illuminate\Http\RedirectResponse;
// Importeer de Enum
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PlanningController extends Controller
{
    private ExternalLocationService $externalLocationService;

    private PlanningFormDataService $planningFormDataService;

    private PlanningLocationTimerService $planningLocationTimerService;

    private PlanningLocationSyncService $planningLocationSyncService;

    private PlanningTaskCreationService $planningTaskCreationService;

    private PlanningTaskUpdateService $planningTaskUpdateService;

    private PlanningShowDataService $planningShowDataService;

    private PlanningCompletionService $planningCompletionService;

    public function __construct(
        private ?TravelTimeService $travelTimeService = null,
        ?ExternalLocationService $externalLocationService = null,
        ?PlanningFormDataService $planningFormDataService = null,
        ?PlanningLocationTimerService $planningLocationTimerService = null,
        ?PlanningLocationSyncService $planningLocationSyncService = null,
        ?PlanningTaskCreationService $planningTaskCreationService = null,
        ?PlanningTaskUpdateService $planningTaskUpdateService = null,
        ?PlanningShowDataService $planningShowDataService = null,
        ?PlanningCompletionService $planningCompletionService = null
    ) {
        // Allow container-bound mocks (including anonymous classes) to be injected in tests
        $this->travelTimeService = $travelTimeService ?: app(TravelTimeService::class);
        $this->externalLocationService = $externalLocationService ?: app(ExternalLocationService::class);
        $this->planningFormDataService = $planningFormDataService ?: app(PlanningFormDataService::class);
        $this->planningLocationTimerService = $planningLocationTimerService ?: app(PlanningLocationTimerService::class);
        $this->planningLocationSyncService = $planningLocationSyncService ?: app(PlanningLocationSyncService::class);
        $this->planningTaskCreationService = $planningTaskCreationService ?: app(PlanningTaskCreationService::class);
        $this->planningTaskUpdateService = $planningTaskUpdateService ?: app(PlanningTaskUpdateService::class);
        $this->planningShowDataService = $planningShowDataService ?: app(PlanningShowDataService::class);
        $this->planningCompletionService = $planningCompletionService ?: app(PlanningCompletionService::class);
    }

    /**
     * Overview of plannings that have tasks pending review.
     */
    public function review(Request $request): View
    {
        $planningsQuery = Planning::with(['locations', 'users'])
            ->withCount([
                'planningTasks as review_tasks_count' => function ($q) {
                    $q->where('status', TaskStatus::REVIEW->value);
                },
            ])
            // Include plannings that either have tasks pending review OR are awaiting end checklist approval
            ->where(function ($query) {
                $query->whereHas('planningTasks', function ($q) {
                    $q->where('status', TaskStatus::REVIEW->value);
                })
                    ->orWhere('status', 'pending_end_checklist');
            })
            ->orderByDesc('planned_date');
        $perPage = $this->resolvePerPage($request, $planningsQuery);
        $plannings = $planningsQuery->paginate($perPage)->withQueryString();

        return view($this->viewName('plannings.review'), compact('plannings'));
    }

    /**
     * Update actual on-location time (HH:mm) for a given location in this planning.
     */
    public function updateLocationActualTime(Request $request, Planning $planning, Location $location): JsonResponse|RedirectResponse
    {
        return $this->planningLocationTimerService->updateLocationActualTime($request, $planning, $location);
    }

    /**
     * Update actual travel time to a destination location (HH:mm).
     */
    public function updateTravelToTime(Request $request, Planning $planning, Location $location): JsonResponse|RedirectResponse
    {
        return $this->planningLocationTimerService->updateTravelToTime($request, $planning, $location);
    }

    /**
     * Update actual return travel time (HH:mm).
     */
    public function updateTravelBackTime(Request $request, Planning $planning): JsonResponse|RedirectResponse
    {
        return $this->planningLocationTimerService->updateTravelBackTime($request, $planning);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $sortBy = $request->input('sort_by', 'planned_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $searchTerm = $request->input('search_term', '');
        $activeFilter = $request->input('filter'); // For status filtering
        $plannedDate = $request->input('planned_date'); // For date filtering
        $awaitingApproval = $request->boolean('awaiting_approval'); // Show plannings with tasks waiting for approval

        $query = Planning::with(['locations', 'users'])->withCount('planningTasks');

        $user = Auth::user();
        if ($user && $user->role !== Role::ADMIN) {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Search functionality
        if (! empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('notes', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('locations', function ($locationQuery) use ($searchTerm) {
                        $locationQuery->where('name', 'LIKE', "%{$searchTerm}%");
                    });
            });
        }

        // Filter functionality (e.g., by status)
        if ($activeFilter) {
            // Assuming 'status' is a column in the plannings table
            // Example: ?filter=open, ?filter=completed
            $query->where('status', $activeFilter);
        }

        if ($plannedDate) {
            $query->whereDate('planned_date', $plannedDate);
        }

        // Awaiting approval filter: plannings having at least one planning task in review status
        if ($awaitingApproval) {
            $query->whereHas('planningTasks', function ($q) {
                $q->where('status', TaskStatus::REVIEW->value);
            });
        }

        // Sorting logic
        $validSortColumns = ['planned_date', 'status', 'created_at', 'planning_tasks_count'];
        if (! in_array($sortBy, $validSortColumns)) {
            $sortBy = 'planned_date';
        }
        if (! in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        $query->orderBy($sortBy, $sortDirection);

        // Add a secondary sort for consistency if not sorting by primary key or a unique date
        if ($sortBy !== 'id' && $sortBy !== 'created_at' && $sortBy !== 'planned_date') {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $this->resolvePerPage($request, $query);
        $plannings = $query->paginate($perPage)->withQueryString();

        return view($this->viewName('plannings.index'), compact(
            'plannings',
            'sortBy',
            'sortDirection',
            'searchTerm',
            'activeFilter',
            'awaitingApproval'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        $users = User::all();
        $locations = Location::with('defaultTasks')->orderBy('name')->get();
        $defaultTasksByLocation = $this->planningFormDataService->buildDefaultTasksByLocation($locations);

        // Haal alle backlog taken op die beschikbaar zijn voor planning.
        $all_backlog_tasks = Task::query()
            ->whereIn('status', ['open', 'in_progress', 'rejected', 'in_review'])
            ->where(function ($query) {
                $query->whereIn('status', [TaskStatus::OPEN->value, TaskStatus::IN_REVIEW->value])
                    ->orWhereDoesntHave('planningTasks');
            })
            ->orderByRaw('deadline IS NULL ASC, deadline ASC') // Eerst taken met deadline (eerste deadline eerst)
            ->orderByRaw('CASE status WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END ASC', [
                'open', 'in_review', 'in_progress', 'rejected',
            ]) // Daarna op status: open, in_review, in_progress, rejected
            ->orderBy('priority', 'asc') // Als tie-breaker: priority
            ->orderBy('created_at', 'asc') // Als laatste tie-breaker: created_at
            ->get();

        $backlogTasksByLocation = $this->planningFormDataService->mapBacklogTasksByLocation($all_backlog_tasks);
        $backlogPriorityCountsByLocation = $this->planningFormDataService->computeBacklogPriorityCounts($all_backlog_tasks);
        $backlogTotalEstimatedTimeByLocation = $this->planningFormDataService->computeBacklogTotalEstimated($all_backlog_tasks);

        // Fetch inactive room counts for all locations in one call
        $allInactiveCounts = $this->externalLocationService->fetchInactiveRoomCounts() ?? [];
        $inactiveRoomCountsByLocation = [];
        foreach ($locations as $location) {
            $inactiveRoomCountsByLocation[$location->id] = (int) ($allInactiveCounts[$location->sync_external_id] ?? ($allInactiveCounts[$location->external_id] ?? 0));
        }

        // Sort locations based on priority task counts and then name
        $locations = $this->planningFormDataService->sortLocationsByBacklogCounts($locations, $backlogPriorityCountsByLocation);

        $selected_location_id = $request->query('location_id');

        $requirements = Requirement::orderBy('name')->get();

        $plannedBacklogTasks = $this->planningFormDataService->plannedBacklogTasksMap();

        // Vehicles available for selected/planned date (default should match UI: tomorrow)
        // The create form defaults the planned_date input to tomorrow (now()->addDay()),
        // so align the server-side default to avoid filtering vehicles on the wrong day.
        $selectedDate = $request->input('planned_date') ?? now()->addDay()->toDateString();
        $availableVehicles = $this->planningFormDataService->availableVehiclesForDate($selectedDate);

        return view($this->viewName('plannings.create'), compact(
            'locations',
            'defaultTasksByLocation',
            'backlogTasksByLocation',
            'backlogPriorityCountsByLocation',
            'backlogTotalEstimatedTimeByLocation',
            'inactiveRoomCountsByLocation',
            'selected_location_id',
            'users',
            'plannedBacklogTasks',
            'availableVehicles',
            'requirements'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlanningRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            $planning = Planning::create([
                'planned_date' => $validated['planned_date'],
                'notes' => $validated['notes'] ?? null,
                'start_address' => $validated['start_address'],
                'start_time' => $validated['start_time'] ?? null,
                'created_by' => Auth::id(),
                'vehicle_id' => $validated['vehicle_id'],
            ]);

            // Sync locations with order and per-location check_inactive_spaces
            $this->planningLocationSyncService->sync($planning, $validated['location_ids'], $request->input('location_order'), $request->input('check_inactive_spaces', []));

            // Reload locations to ensure pivot data is up-to-date for createPlanningTasks
            $planning->load('locations');

            // Sync users
            if (! empty($validated['user_ids'])) {
                $planning->users()->sync($validated['user_ids']);
            }

            // Create planning tasks from default and backlog tasks
            $this->planningTaskCreationService->create($planning, $validated);
        });

        return redirect()->route('plannings.index')->with('success', 'Planning succesvol aangemaakt.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Planning $planning): View
    {
        $planning->load([
            'locations',
            'locationTimers',
            'vehicle',
            'planningTasks' => function ($query) {
                $query->with([
                    'task.location',
                    'defaultTask.locations',
                    'specificLocation',
                    // Also eager-load vehicleTask to show vehicle-related planning tasks without N+1
                    'vehicleTask',
                    'planningTaskPhotos',
                    'completions' => function ($completionQuery) {
                        $completionQuery->with(['user', 'photos'])->orderBy('created_at', 'desc');
                    },
                ]);
            },
            // Ensure end checklist items are available on the planning details screen without N+1
            'endChecklistItems' => function ($query) {
                $query->with(['requirement', 'reviewer', 'location', 'uploader', 'photos']);
            },
            'comments.photos',
        ]);

        $showData = $this->planningShowDataService->build($planning);

        return view($this->viewName('plannings.show'), array_merge(['planning' => $planning], $showData));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Planning $planning): View
    {
        $users = User::all();
        $locations = Location::with('defaultTasks')->orderBy('name')->get();
        $defaultTasksByLocation = $this->planningFormDataService->buildDefaultTasksByLocation($locations);

        $planning->load('locations');
        $current_selected_location_ids = $planning->locations->pluck('id')->all();

        // Haal alle backlog taken op die beschikbaar zijn voor planning.
        $availableBacklogTasks = Task::query()
            ->whereIn('status', ['open', 'in_progress', 'rejected', 'in_review'])
            ->where(function ($query) use ($planning) {
                // Open en In Review taken mogen ook al aan andere planningen gekoppeld zijn
                $query->whereIn('status', [TaskStatus::OPEN->value, TaskStatus::IN_REVIEW->value])
                    ->orWhereDoesntHave('planningTasks')
                    ->orWhereHas('planningTasks', function ($planningQuery) use ($planning) {
                        // OF de taak is gekoppeld aan de HUIDIGE planning
                        $planningQuery->where('planning_id', $planning->id);
                    });
            })
            ->orderByRaw('deadline IS NULL ASC, deadline ASC') // Eerst taken met deadline (eerste deadline eerst)
            ->orderByRaw('CASE status WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END ASC', [
                'open', 'in_review', 'in_progress', 'rejected',
            ]) // Daarna op status: open, in_review, in_progress, rejected
            ->orderBy('priority', 'asc') // Als tie-breaker: priority
            ->orderBy('created_at', 'asc') // Als laatste tie-breaker: created_at
            ->get();

        /**
         * @var Collection<int|string, Collection<int, array{
         *   id:int,
         *   title:string,
         *   description:string|null,
         *   priority: array{value:string,label:string},
         *   status: TaskStatus,
         *   deadline: Carbon|null,
         *   estimated_time_minutes:int
         * }>> $backlogTasksByLocation
         */
        $backlogTasksByLocation = $this->planningFormDataService->mapBacklogTasksByLocation($availableBacklogTasks);

        $backlogPriorityCountsByLocation = $this->planningFormDataService->computeBacklogPriorityCounts($availableBacklogTasks);
        $backlogTotalEstimatedTimeByLocation = $this->planningFormDataService->computeBacklogTotalEstimated($availableBacklogTasks);

        // Fetch inactive room counts for all locations in one call
        $allInactiveCounts = $this->externalLocationService->fetchInactiveRoomCounts() ?? [];
        $inactiveRoomCountsByLocation = [];
        foreach ($locations as $location) {
            $inactiveRoomCountsByLocation[$location->id] = (int) ($allInactiveCounts[$location->sync_external_id] ?? ($allInactiveCounts[$location->external_id] ?? 0));
        }

        $current_selected_default_tasks = $planning->planningTasks
            ->whereNotNull('default_task_id')
            ->pluck('default_task_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $current_selected_backlog_tasks = $planning->planningTasks
            ->whereNotNull('task_id')
            ->pluck('task_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        // Sort locations based on priority task counts and then name
        $locations = $this->planningFormDataService->sortLocationsByBacklogCounts($locations, $backlogPriorityCountsByLocation);

        $plannedBacklogTasks = $this->planningFormDataService->plannedBacklogTasksMap($planning, true);

        // Vehicles available for the planning date; include currently assigned vehicle if set
        $selectedDate = $planning->planned_date->toDateString();
        $availableVehicles = $this->planningFormDataService->availableVehiclesForDate($selectedDate, $planning);
        if ($planning->vehicle && ! $availableVehicles->contains('id', $planning->vehicle->id)) {
            $availableVehicles->push($planning->vehicle);
            $availableVehicles = $availableVehicles->sortBy('name')->values();
        }

        $requirements = Requirement::orderBy('name')->get();

        return view($this->viewName('plannings.edit'), compact(
            'planning',
            'locations',
            'defaultTasksByLocation',
            'backlogTasksByLocation',
            'backlogPriorityCountsByLocation',
            'backlogTotalEstimatedTimeByLocation',
            'inactiveRoomCountsByLocation',
            'current_selected_location_ids',
            'current_selected_default_tasks',
            'current_selected_backlog_tasks',
            'users',
            'plannedBacklogTasks',
            'availableVehicles',
            'requirements',
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlanningRequest $request, Planning $planning): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($planning, $validated, $request) {
            $planning->update([
                'planned_date' => $validated['planned_date'],
                'notes' => $validated['notes'] ?? null,
                'start_address' => $validated['start_address'],
                'start_time' => $validated['start_time'] ?? null,
                'vehicle_id' => $validated['vehicle_id'],
            ]);

            // Sync locations with order and per-location check_inactive_spaces
            $this->planningLocationSyncService->sync($planning, $validated['location_ids'], $request->input('location_order'), $request->input('check_inactive_spaces', []));

            // Reload locations to ensure pivot data is up-to-date for updatePlanningTasks
            $planning->load('locations');

            // Sync users
            $planning->users()->sync($validated['user_ids'] ?? []);

            // Handle task updates
            $this->planningTaskUpdateService->update($planning, $validated);
        });

        return redirect()->route('plannings.show', $planning)->with('success', 'Planning succesvol bijgewerkt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Planning $planning): RedirectResponse
    {
        $planning->delete();

        return redirect()->route('plannings.index')->with('success', 'Planning succesvol verwijderd.');
    }

    /**
     * Send notification emails to all users assigned to this planning.
     */
    public function sendNotifications(Planning $planning): RedirectResponse
    {
        // Load the necessary relationships
        $planning->load(['users', 'locations', 'planningTasks']);

        // Check if there are users assigned to this planning
        if ($planning->users->isEmpty()) {
            return redirect()->back()->with('error', 'Er zijn geen gebruikers toegewezen aan deze planning.');
        }

        $sentCount = 0;
        foreach ($planning->users as $user) {
            try {
                Mail::to($user->email)->send(new PlanningReadyNotificationMail($planning));
                $sentCount++;
            } catch (\Exception $e) {
                // Log the error but continue sending to other users
                Log::error('Failed to send planning notification to user '.$user->id.': '.$e->getMessage());
            }
        }

        if ($sentCount > 0) {
            $message = "Notificatie succesvol verstuurd naar {$sentCount} gebruiker(s).";

            return redirect()->back()->with('success', $message);
        } else {
            return redirect()->back()->with('error', 'Er is een fout opgetreden bij het versturen van de notificaties.');
        }
    }

    /**
     * Mark a planning as completed, regardless of task status.
     */
    public function complete(Planning $planning): RedirectResponse
    {
        $this->planningCompletionService->complete($planning);

        return redirect()->back()->with('success', 'Planning is afgerond. Niet-afgeronde standaardtaken zijn vrijgegeven voor herplanning.');
    }

    /**
     * Update internal notes for a planning.
     */
    public function updateInternalNotes(Request $request, Planning $planning): RedirectResponse
    {
        $validated = $request->validate([
            'internal_notes' => 'nullable|string',
        ]);

        $planning->update([
            'internal_notes' => $validated['internal_notes'],
        ]);

        return redirect()->back()->with('success', 'Interne notitie succesvol bijgewerkt.');
    }

    /**
     * Get timer data for a specific location in a planning.
     */
    public function getLocationTimer(Planning $planning, int|string $locationId): JsonResponse
    {
        [$actualLocationId, $locationType] = $this->planningLocationTimerService->resolveTimerTarget($locationId);
        $timer = $this->planningLocationTimerService->findTimer($planning, $actualLocationId, $locationType);

        if (! $timer) {
            return response()->json([
                'started_at' => null,
                'ended_at' => null,
                'total_duration' => 0,
            ]);
        }

        return $this->planningLocationTimerService->buildTimerJson($timer);
    }

    /**
     * Start timer for a specific location in a planning.
     */
    public function startLocationTimer(Planning $planning, int|string $locationId): JsonResponse
    {
        [$actualLocationId, $locationType] = $this->planningLocationTimerService->resolveTimerTarget($locationId);
        $timer = $this->planningLocationTimerService->findTimer($planning, $actualLocationId, $locationType);
        if ($timer) {
            $timer->update([
                'started_at' => now(),
                'ended_at' => null,
            ]);
        } else {
            $timer = $this->planningLocationTimerService->ensureTimerStarted($planning, $actualLocationId, $locationType);
        }

        return response()->json([
            'success' => true,
            'timer' => [
                'started_at' => $timer->started_at->toISOString(),
                'total_duration' => $timer->total_duration_seconds,
            ],
        ]);
    }

    /**
     * Stop timer for a specific location in a planning.
     */
    public function stopLocationTimer(Request $request, Planning $planning, int|string $locationId): JsonResponse
    {
        $request->validate([
            'total_duration' => 'required|integer|min:0',
        ]);

        [$actualLocationId, $locationType] = $this->planningLocationTimerService->resolveTimerTarget($locationId);
        $timer = $this->planningLocationTimerService->findTimer($planning, $actualLocationId, $locationType);

        if (! $timer) {
            return response()->json(['error' => 'Timer not found'], 404);
        }

        // End the timer and persist total duration
        $timer->update([
            'ended_at' => now(),
            'total_duration_seconds' => $request->input('total_duration'),
        ]);

        return response()->json([
            'success' => true,
            'timer' => [
                'started_at' => $timer->started_at->toISOString(),
                'ended_at' => $timer->ended_at->toISOString(),
                'total_duration' => $timer->total_duration_seconds,
            ],
        ]);
    }

    /**
     * Restart timer for a specific location in a planning.
     * This preserves the previous duration and starts counting again.
     */
    public function restartLocationTimer(Request $request, Planning $planning, int|string $locationId): JsonResponse
    {
        return $this->planningLocationTimerService->restartLocationTimer($request, $planning, $locationId);
    }
}
