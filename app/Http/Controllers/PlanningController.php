<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Http\Requests\StorePlanningRequest;
use App\Http\Requests\UpdatePlanningRequest;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningLocationTimer;
use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TravelTimeService;
use App\Mail\PlanningReadyNotificationMail;
use App\Enums\TaskStatus;
use App\Enums\Role;
use App\Models\PlanningTask;
use App\Models\Requirement;
use App\Models\VehicleTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// For database transactions
use Illuminate\Support\Facades\Auth;

// Importeer de Enum
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __construct(
        private ?TravelTimeService $travelTimeService = null
    )
    {
        // Allow container-bound mocks (including anonymous classes) to be injected in tests
        $this->travelTimeService = $travelTimeService ?: app(TravelTimeService::class);
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
        $time = $this->validateTimeInput($request);
        $seconds = $this->parseHHMMToSeconds($time);

        // Set the total on-location time directly to the provided HH:mm (interpreted as total desired time on location)
        $timer = PlanningLocationTimer::firstOrNew([
            'planning_id' => $planning->id,
            'location_id' => $location->id,
            'location_type' => 'location',
        ]);
        $timer->total_duration_seconds = max(0, $seconds);
        $timer->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'seconds' => $timer->total_duration_seconds,
                'hhmm' => $this->formatSecondsHHMM($timer->total_duration_seconds),
            ]);
        }

        return back()->with('success', 'Tijd op locatie bijgewerkt.');
    }

    /**
     * Update actual travel time to a destination location (HH:mm).
     */
    public function updateTravelToTime(Request $request, Planning $planning, Location $location): JsonResponse|RedirectResponse
    {
        $time = $this->validateTimeInput($request);
        $seconds = $this->parseHHMMToSeconds($time);

        $timer = PlanningLocationTimer::firstOrNew([
            'planning_id' => $planning->id,
            'location_id' => $location->id,
            'location_type' => 'travel',
        ]);
        $timer->total_duration_seconds = max(0, $seconds);
        $timer->save();

        // Splitting travel time among locations has been removed; we only store per-trip travel timers.

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'seconds' => $timer->total_duration_seconds,
                'hhmm' => $this->formatSecondsHHMM($timer->total_duration_seconds),
            ]);
        }

        return back()->with('success', 'Reistijd bijgewerkt.');
    }

    /**
     * Update actual return travel time (HH:mm).
     */
    public function updateTravelBackTime(Request $request, Planning $planning): JsonResponse|RedirectResponse
    {
        $time = $this->validateTimeInput($request);
        $seconds = $this->parseHHMMToSeconds($time);

        $timer = PlanningLocationTimer::firstOrNew([
            'planning_id' => $planning->id,
            'location_id' => null,
            'location_type' => 'travel_back',
        ]);
        $timer->total_duration_seconds = max(0, $seconds);
        $timer->save();

        // Splitting travel time among locations has been removed; no redistribution is performed.

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'seconds' => $timer->total_duration_seconds,
                'hhmm' => $this->formatSecondsHHMM($timer->total_duration_seconds),
            ]);
        }

        return back()->with('success', 'Reistijd terug bijgewerkt.');
    }

    private function parseHHMMToSeconds(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        $h = max(0, $h);
        $m = max(0, min(59, $m));
        return $h * 3600 + $m * 60;
    }

    private function formatSecondsHHMM(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        return sprintf('%02d:%02d', $h, $m);
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
        if (!empty($searchTerm)) {
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
        if (!in_array($sortBy, $validSortColumns)) {
            $sortBy = 'planned_date';
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
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
        $defaultTasksByLocation = $this->buildDefaultTasksByLocation($locations);

        // Haal alle backlog taken op die beschikbaar zijn voor planning.
        $all_backlog_tasks = Task::query()
            ->whereIn('status', ['open', 'in_progress', 'rejected', 'in_review'])
            ->where(function ($query) {
                $query->whereIn('status', [TaskStatus::OPEN->value, TaskStatus::IN_REVIEW->value])
                    ->orWhereDoesntHave('planningTasks');
            })
            ->orderByRaw('deadline IS NULL ASC, deadline ASC') // Eerst taken met deadline (eerste deadline eerst)
            ->orderByRaw('CASE status WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END ASC', [
                'open', 'in_review', 'in_progress', 'rejected'
            ]) // Daarna op status: open, in_review, in_progress, rejected
            ->orderBy('priority', 'asc') // Als tie-breaker: priority
            ->orderBy('created_at', 'asc') // Als laatste tie-breaker: created_at
            ->get();

        $backlogTasksByLocation = $this->mapBacklogTasksByLocation($all_backlog_tasks);
        $backlogPriorityCountsByLocation = $this->computeBacklogPriorityCounts($all_backlog_tasks);
        $backlogTotalEstimatedTimeByLocation = $this->computeBacklogTotalEstimated($all_backlog_tasks);

        // Sort locations based on priority task counts and then name
        $locations = $this->sortLocationsByBacklogCounts($locations, $backlogPriorityCountsByLocation);

        $selected_location_id = $request->query('location_id');

        $requirements = Requirement::orderBy('name')->get();

        $plannedBacklogTasks = $this->plannedBacklogTasksMap();

        // Vehicles available for selected/planned date (default should match UI: tomorrow)
        // The create form defaults the planned_date input to tomorrow (now()->addDay()),
        // so align the server-side default to avoid filtering vehicles on the wrong day.
        $selectedDate = $request->input('planned_date') ?? now()->addDay()->toDateString();
        $availableVehicles = $this->availableVehiclesForDate($selectedDate);

        return view($this->viewName('plannings.create'), compact(
            'locations',
            'defaultTasksByLocation',
            'backlogTasksByLocation',
            'backlogPriorityCountsByLocation',
            'backlogTotalEstimatedTimeByLocation',
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
                'notes' => $validated['notes'],
                'start_address' => $validated['start_address'],
                'start_time' => $validated['start_time'],
                'created_by' => \Illuminate\Support\Facades\Auth::id(),
                'vehicle_id' => $validated['vehicle_id'],
            ]);

            // Sync locations with order
            $this->syncLocations($planning, $validated['location_ids'], $request->input('location_order'));

            // Sync users
            if (!empty($validated['user_ids'])) {
                $planning->users()->sync($validated['user_ids']);
            }

            // Create planning tasks from default and backlog tasks
            $this->createPlanningTasks($planning, $validated);
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

        // Calculate travel times between locations
        $travelTimes = null;
        if ($planning->locations->count() > 1) {
            $travelTimes = $this->travelTimeService->calculateTravelTimesForSequence(
                $planning->locations->all(),
                $planning->start_address
            );
        }

        // Calculate task times
        $totalTaskMinutes = $planning->planningTasks->sum(function ($planningTask) {
            if ($planningTask->task && isset($planningTask->task->estimated_time_minutes)) {
                return (int)$planningTask->task->estimated_time_minutes;
            } elseif ($planningTask->defaultTask && isset($planningTask->defaultTask->estimated_time_minutes)) {
                return (int)$planningTask->defaultTask->estimated_time_minutes;
            }
            return 0;
        });

        // Build timers lookups
        $onLocationTimers = $planning->locationTimers->where('location_type', 'location')->keyBy('location_id');
        $travelToTimers = $planning->locationTimers->where('location_type', 'travel')->keyBy('location_id');
        $travelBackTimer = $planning->locationTimers->firstWhere('location_type', 'travel_back');

        // Calculate time overview (planned)
        $timeOverview = [
            'task_minutes' => $totalTaskMinutes,
            'travel_minutes' => $travelTimes ? $travelTimes['total_duration_minutes'] : 0,
            'total_minutes' => $totalTaskMinutes + ($travelTimes ? $travelTimes['total_duration_minutes'] : 0),
        ];

        // Calculate actual totals from timers
        $actualTravelSeconds = $planning->locationTimers
            ->whereIn('location_type', ['travel', 'travel_back'])
            ->sum('total_duration_seconds');
        $actualOnLocationSeconds = $planning->locationTimers
            ->where('location_type', 'location')
            ->sum('total_duration_seconds');

        $actualTotals = [
            'travel_seconds' => (int)$actualTravelSeconds,
            'on_location_seconds' => (int)$actualOnLocationSeconds,
        ];

        return view($this->viewName('plannings.show'), compact('planning', 'travelTimes', 'timeOverview', 'onLocationTimers', 'travelToTimers', 'travelBackTimer', 'actualTotals'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Planning $planning): View
    {
        $users = User::all();
        $locations = Location::with('defaultTasks')->orderBy('name')->get();
        $defaultTasksByLocation = $this->buildDefaultTasksByLocation($locations);

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
                'open', 'in_review', 'in_progress', 'rejected'
            ]) // Daarna op status: open, in_review, in_progress, rejected
            ->orderBy('priority', 'asc') // Als tie-breaker: priority
            ->orderBy('created_at', 'asc') // Als laatste tie-breaker: created_at
            ->get();

        /**
         * @var \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, array{
         *   id:int,
         *   title:string,
         *   description:string|null,
         *   priority: array{value:string,label:string},
         *   status: \App\Enums\TaskStatus,
         *   deadline: \Carbon\Carbon|null,
         *   estimated_time_minutes:int
         * }>> $backlogTasksByLocation
         */
        $backlogTasksByLocation = $this->mapBacklogTasksByLocation($availableBacklogTasks);

        $backlogPriorityCountsByLocation = $this->computeBacklogPriorityCounts($availableBacklogTasks);
        $backlogTotalEstimatedTimeByLocation = $this->computeBacklogTotalEstimated($availableBacklogTasks);

        $current_selected_default_tasks = $planning->planningTasks
            ->whereNotNull('default_task_id')
            ->pluck('default_task_id')
            ->map(fn($id) => (string)$id)
            ->all();

        $current_selected_backlog_tasks = $planning->planningTasks
            ->whereNotNull('task_id')
            ->pluck('task_id')
            ->map(fn($id) => (string)$id)
            ->all();

        // Sort locations based on priority task counts and then name
        $locations = $this->sortLocationsByBacklogCounts($locations, $backlogPriorityCountsByLocation);

        $plannedBacklogTasks = $this->plannedBacklogTasksMap($planning, true);

        // Vehicles available for the planning date; include currently assigned vehicle if set
        $selectedDate = $planning->planned_date->toDateString();
        $availableVehicles = $this->availableVehiclesForDate($selectedDate, $planning);
        if ($planning->vehicle && !$availableVehicles->contains('id', $planning->vehicle->id)) {
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
                'notes' => $validated['notes'],
                'start_address' => $validated['start_address'],
                'start_time' => $validated['start_time'],
                'vehicle_id' => $validated['vehicle_id'],
            ]);

            // Sync locations with order
            $this->syncLocations($planning, $validated['location_ids'], $request->input('location_order'));

            // Sync users
            $planning->users()->sync($validated['user_ids'] ?? []);

            // Handle task updates
            $this->updatePlanningTasks($planning, $validated);
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
                Log::error('Failed to send planning notification to user ' . $user->id . ': ' . $e->getMessage());
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
        DB::transaction(function () use ($planning) {
            // 0) Eerst: automatisch goedkeuren van ingediende taken (in review)
            $submittedTasks = $planning->planningTasks()
                ->whereIn('status', [
                    \App\Enums\TaskStatus::REVIEW->value,
                    \App\Enums\TaskStatus::IN_REVIEW->value,
                ])->get();

            if ($submittedTasks->isNotEmpty()) {
                // Hergebruik de bestaande approve-flow (mailing, linked vehicle sync, etc.)
                $ptController = new \App\Http\Controllers\PlanningTaskController();
                foreach ($submittedTasks as $pt) {
                    try {
                        // Lege request; we willen alleen de statuswijziging en side-effects
                        $req = new \Illuminate\Http\Request();
                        // Geef planning_id mee zodat eventuele redirects binnen approve() consistent zijn
                        $req->merge(['planning_id' => $planning->id]);
                        $ptController->approve($req, $pt);
                    } catch (\Throwable $e) {
                        // Faal niet de gehele afronding op één taak; log en ga door
                        Log::warning('Automatische goedkeuring bij afronden planning faalde', [
                            'planning_id' => $planning->id,
                            'planning_task_id' => $pt->id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            // Delete all uncompleted tasks that were created from default tasks
            $planning->cleanupUncompletedDefaultTasks();

            // 2) Voor backlog-gelinkte plannings-taken die nog niet voltooid zijn: maak ze opnieuw planbaar
            // - Set the original backlog task status back to OPEN
            // - Detach the uncompleted planning task from this (now completed) planning
            $uncompletedBacklogPlanningTasks = $planning->planningTasks()
                ->whereNotNull('task_id')
                ->where('status', '!=', \App\Enums\TaskStatus::COMPLETED->value)
                ->get();

            foreach ($uncompletedBacklogPlanningTasks as $pt) {
                if ($pt->task) {
                    // Check if this task is a default task (by title match for the location)
                    // If it is, we might want to delete it instead of freeing it,
                    // but cleanupUncompletedDefaultTasks already handles "floating" ones.
                    // However, this task IS currently linked, so it's not "floating" yet.

                    $isDefaultTask = DefaultTask::where(function ($query) use ($pt) {
                        $query->where('applies_to_all_locations', true)
                            ->orWhereHas('locations', function ($q) use ($pt) {
                                $q->where('locations.id', $pt->location_id);
                            });
                    })->where('title', $pt->title)->exists();

                    if ($isDefaultTask) {
                        $pt->task->delete();
                    } else {
                        $pt->task->update(['status' => \App\Enums\TaskStatus::OPEN]);
                    }
                }
                // Remove the link from this completed planning (geplande taak verwijderen)
                $pt->delete();
            }

            $planning->update([
                'status' => 'completed',
            ]);
        });

        return redirect()->back()->with('success', 'Planning is afgerond. Niet-afgeronde standaardtaken zijn vrijgegeven voor herplanning.');
    }

    /**
     * Get timer data for a specific location in a planning.
     */
    public function getLocationTimer(Planning $planning, int|string $locationId): JsonResponse
    {
        [$actualLocationId, $locationType] = $this->resolveTimerTarget($locationId);
        $timer = $this->findTimer($planning, $actualLocationId, $locationType);

        if (!$timer) {
            return response()->json([
                'started_at' => null,
                'ended_at' => null,
                'total_duration' => 0,
            ]);
        }

        return $this->buildTimerJson($timer);
    }

    /**
     * Start timer for a specific location in a planning.
     */
    public function startLocationTimer(Planning $planning, int|string $locationId): JsonResponse
    {
        [$actualLocationId, $locationType] = $this->resolveTimerTarget($locationId);
        $timer = $this->findTimer($planning, $actualLocationId, $locationType);
        if ($timer) {
            $timer->update([
                'started_at' => now(),
                'ended_at' => null,
            ]);
        } else {
            $timer = $this->ensureTimerStarted($planning, $actualLocationId, $locationType);
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

        [$actualLocationId, $locationType] = $this->resolveTimerTarget($locationId);
        $timer = $this->findTimer($planning, $actualLocationId, $locationType);

        if (!$timer) {
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
        $request->validate([
            'previous_duration' => 'required|integer|min:0',
        ]);

        [$actualLocationId, $locationType] = $this->resolveTimerTarget($locationId);
        $timer = $this->findTimer($planning, $actualLocationId, $locationType);

        if (!$timer) {
            return response()->json(['error' => 'Timer not found'], 404);
        }

        // Restart the timer - set new start time, clear end time, preserve previous duration
        $timer->update([
            'started_at' => now(),
            'ended_at' => null,
            'total_duration_seconds' => $request->input('previous_duration'), // Keep the accumulated time
        ]);

        return response()->json([
            'success' => true,
            'timer' => [
                'started_at' => $timer->started_at->toISOString(),
                'ended_at' => null,
                'total_duration' => $timer->total_duration_seconds,
            ],
        ]);
    }

    /**
     * Shared helpers (private) to keep controller lean and DRY.
     */
    /**
     * @return array{0:int|string|null,1:string}
     */
    private function resolveTimerTarget(int|string $locationId): array
    {
        if ($locationId === 'backlog') {
            return [null, 'backlog'];
        }
        if (is_string($locationId) && str_starts_with($locationId, 'travel_to_')) {
            return [str_replace('travel_to_', '', $locationId), 'travel'];
        }
        if ($locationId === 'travel_back') {
            return [null, 'travel_back'];
        }
        return [$locationId, 'location'];
    }

    private function findTimer(Planning $planning, int|string|null $actualLocationId, string $locationType): ?PlanningLocationTimer
    {
        return PlanningLocationTimer::where('planning_id', $planning->id)
            ->where('location_id', $actualLocationId)
            ->where('location_type', $locationType)
            ->first();
    }

    private function ensureTimerStarted(Planning $planning, int|string|null $actualLocationId, string $locationType): PlanningLocationTimer
    {
        return PlanningLocationTimer::create([
            'planning_id' => $planning->id,
            'location_id' => $actualLocationId,
            'location_type' => $locationType,
            'started_at' => now(),
            'ended_at' => null,
            'total_duration_seconds' => 0,
        ]);
    }

    private function buildTimerJson(PlanningLocationTimer $timer): JsonResponse
    {
        return response()->json([
            'started_at' => $timer->started_at?->toISOString(),
            'ended_at' => $timer->ended_at?->toISOString(),
            'total_duration' => $timer->total_duration_seconds,
        ]);
    }

    /**
     * Validate the required HH:mm time input and return it as string.
     * Keeps validation behavior identical across endpoints.
     */
    private function validateTimeInput(Request $request): string
    {
        $request->validate([
            'time' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
        ]);

        // string() returns Str, but cast to string for clarity and phpstan
        return (string) $request->string('time');
    }

    /**
     * @param \Illuminate\Support\Collection<int, Location> $locations
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, array{id:int,title:string,description:string,estimated_time_minutes:int,applies_to_all_locations:bool,is_always_included:bool}>>
     */
    private function buildDefaultTasksByLocation($locations)
    {
        return $locations->mapWithKeys(function ($location) {
            $locationSpecificTasks = $location->defaultTasks;
            $allLocationTasks = DefaultTask::forAllLocations()->get();
            $allTasks = $locationSpecificTasks->merge($allLocationTasks)->unique('id');
            return [$location->id => $allTasks->map(function ($task) use ($location) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    // DefaultTask::description is non-null in model typing; normalize to string for views
                    'description' => (string) $task->description,
                    'estimated_time_minutes' => $task->calculateEstimatedTime($location),
                    'applies_to_all_locations' => $task->applies_to_all_locations ?? false,
                    'is_always_included' => $task->is_always_included ?? false,
                ];
            })];
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Task> $tasks
     * @return \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, array{id:int,title:string,description:string,priority: array{value:string,label:string},status: TaskStatus,deadline: ?\Carbon\Carbon,estimated_time_minutes:int}>>
     */
    private function mapBacklogTasksByLocation($tasks)
    {
        return $tasks->groupBy('location_id')
            ->map(static function (\Illuminate\Database\Eloquent\Collection $grouped, int|string|null $key): \Illuminate\Support\Collection {
                return $grouped->map(static function (Task $task): array {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        // Normalize to string to match model typing and UI expectations
                        'description' => (string) $task->description,
                        'priority' => [
                            'value' => $task->priority->value,
                            'label' => $task->priority->label(),
                        ],
                        'status' => $task->status,
                        'deadline' => $task->deadline,
                        'estimated_time_minutes' => $task->estimated_time_minutes ?? 0,
                    ];
                })->toBase();
            });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Task> $tasks
     * @return \Illuminate\Support\Collection<int|string, array{
     *   high:int, normal:int, low:int
     * }>|\Illuminate\Support\Collection<int|string, array<string,int>>
     */
    private function computeBacklogPriorityCounts($tasks): \Illuminate\Support\Collection
    {
        return $tasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                return [
                    TaskPriority::HIGH->value => $tasks_for_location->where('priority', TaskPriority::HIGH)->count(),
                    TaskPriority::NORMAL->value => $tasks_for_location->where('priority', TaskPriority::NORMAL)->count(),
                    TaskPriority::LOW->value => $tasks_for_location->where('priority', TaskPriority::LOW)->count(),
                ];
            });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Task> $tasks
     * @return \Illuminate\Support\Collection<int|string, int>
     */
    private function computeBacklogTotalEstimated($tasks): \Illuminate\Support\Collection
    {
        return $tasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                return $tasks_for_location->sum('estimated_time_minutes');
            });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Location>|\Illuminate\Support\Collection<int, Location> $locations
     * @param \Illuminate\Support\Collection<int|string, array<string,int>> $backlogPriorityCountsByLocation
     * @return \Illuminate\Support\Collection<int, Location>
     */
    private function sortLocationsByBacklogCounts($locations, $backlogPriorityCountsByLocation): \Illuminate\Support\Collection
    {
        return $locations->sortBy(function ($location) use ($backlogPriorityCountsByLocation) {
            $counts = $backlogPriorityCountsByLocation[$location->id] ?? [
                TaskPriority::HIGH->value => 0,
                TaskPriority::NORMAL->value => 0,
                TaskPriority::LOW->value => 0,
            ];
            return [
                -($counts[TaskPriority::HIGH->value]),
                -($counts[TaskPriority::NORMAL->value]),
                -($counts[TaskPriority::LOW->value]),
                $location->name,
            ];
        })->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{planning_id:int,planning_title:string}>
     */
    private function plannedBacklogTasksMap(?Planning $planning = null, bool $excludeCurrent = false)
    {
        $query = PlanningTask::whereNotNull('task_id')->with('planning:id,planned_date');
        if ($planning && $excludeCurrent) {
            $query->where('planning_id', '!=', $planning->id);
        }
        return $query->get()->mapWithKeys(function ($planningTask) {
            return [(int) $planningTask->task_id => [
                'planning_id' => $planningTask->planning->id,
                'planning_title' => $planningTask->planning->planned_date->format('d-m-Y'),
            ]];
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Vehicle>
     */
    private function availableVehiclesForDate(string $selectedDate, ?Planning $planning = null): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::query()
            ->whereDoesntHave('plannings', function ($q) use ($selectedDate, $planning) {
                $q->whereDate('planned_date', $selectedDate);
                if ($planning) {
                    $q->where('plannings.id', '!=', $planning->id);
                }
                $q->where(function ($qq) {
                    $qq->whereNull('status')
                        ->orWhere('status', '!=', 'completed');
                });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param array<int,int> $locationIds
     */
    private function syncLocations(Planning $planning, array $locationIds, ?string $locationOrder): void
    {
        // Parse incoming explicit order from hidden field and preserve it strictly
        $orderedIds = $locationOrder ? array_values(array_filter(
            array_map(static fn($v) => (int)trim((string)$v), explode(',', $locationOrder)),
            static fn($v) => $v > 0
        )) : [];

        // Build final order: keep only selected IDs in the exact user-provided order
        $selectedSet = array_flip(array_map('intval', $locationIds));
        $finalOrderedIds = [];
        foreach ($orderedIds as $id) {
            if (isset($selectedSet[$id]) && !in_array($id, $finalOrderedIds, true)) {
                $finalOrderedIds[] = $id;
            }
        }
        // Append any selected IDs that might be missing (e.g., newly selected without drag)
        foreach ($locationIds as $id) {
            $iid = (int)$id;
            if (!in_array($iid, $finalOrderedIds, true)) {
                $finalOrderedIds[] = $iid;
            }
        }

        // Map to pivot payload with 0-based sort_order
        $locationsToSync = [];
        foreach ($finalOrderedIds as $index => $locationId) {
            $locationsToSync[(int)$locationId] = ['sort_order' => $index];
        }

        // Sync pivot table
        $planning->locations()->sync($locationsToSync);
    }

    /**
     * @param array{
     *   selected_default_tasks?: array<int,int>,
     *   selected_backlog_tasks?: array<int,int>,
     *   location_ids?: array<int,int>
     * } $validatedData
     */
    private function createPlanningTasks(Planning $planning, array $validatedData): void
    {
        // 1) Inject open vehicle tasks for the assigned vehicle so they appear first
        if ($planning->vehicle_id) {
            $openVehicleTasks = \App\Models\VehicleTask::where('vehicle_id', $planning->vehicle_id)
                ->where('status', \App\Enums\TaskStatus::OPEN->value)
                ->orderBy('created_at')
                ->get();

            foreach ($openVehicleTasks as $vt) {
                $planning->planningTasks()->create([
                    'vehicle_task_id' => $vt->id,
                    'title' => $vt->title,
                    // Some vehicle tasks may not have a description; DB column is NOT NULL
                    'description' => $vt->description ?? '',
                    'status' => \App\Enums\TaskStatus::OPEN,
                    'estimated_time_minutes' => $vt->estimated_time_minutes,
                    'is_vehicle_task' => true,
                ]);
            }
        }

        if (!empty($validatedData['selected_default_tasks']) && !empty($validatedData['location_ids'])) {
            /** @var array<int,int> $locIds */
            $locIds = array_map('intval', $validatedData['location_ids']);
            $selected_location_ids = collect($locIds);
            /** @var array<int,int> $defaultIds */
            $defaultIds = array_map('intval', $validatedData['selected_default_tasks']);
            $default_task_templates = DefaultTask::with('locations')->findMany($defaultIds);
            $locations = Location::findMany($selected_location_ids);

            foreach ($selected_location_ids as $location_id) {
                $location = $locations->firstWhere('id', $location_id);
                if (!$location) continue;

                foreach ($default_task_templates as $template) {
                    if ($template->locations->contains('id', $location_id)) {
                        $estimatedTime = $template->calculateEstimatedTime($location);

                        // Duplicate DefaultTask to a normal Task
                        $newTask = Task::create([
                            'location_id' => $location_id,
                            'title' => $template->title,
                            'description' => $template->description ?? '',
                            'feedback_information' => $template->feedback_information,
                            'feedback_owner_name' => $template->feedback_owner_name,
                            'feedback_emails' => $template->feedback_emails,
                            'estimated_time_minutes' => $estimatedTime,
                            'status' => \App\Enums\TaskStatus::OPEN,
                            'priority' => \App\Enums\TaskPriority::NORMAL,
                            'end_day_action_title' => $template->end_day_action_title,
                            'end_day_action_description' => $template->end_day_action_description,
                            'created_by' => Auth::id(),
                        ]);

                        // Sync requirements from template to new task
                        if ($template->requirements()->exists()) {
                            $newTask->requirements()->sync($template->requirements->pluck('id'));
                        }

                        $planning->planningTasks()->create([
                            'location_id' => $location_id,
                            'task_id' => $newTask->id,
                            'title' => $template->title,
                            // Ensure non-null description for NOT NULL column
                            'description' => $template->description ?? '',
                            'feedback_information' => $template->feedback_information,
                            'feedback_owner_name' => $template->feedback_owner_name,
                            'feedback_emails' => $template->feedback_emails,
                            'estimated_time_minutes' => $estimatedTime,
                        ]);
                    }
                }
            }
        }

        // Logic for adding backlog tasks
        if (!empty($validatedData['selected_backlog_tasks'])) {
            /** @var array<int,int> $backlogIds */
            $backlogIds = array_map('intval', $validatedData['selected_backlog_tasks']);
            $backlogTasks = Task::query()
                ->whereIn('id', $backlogIds)
                ->get();
            foreach ($backlogTasks as $backlogTask) {
                $planning->planningTasks()->create([
                    'task_id' => $backlogTask->id,
                    'title' => $backlogTask->title,
                    // Ensure non-null description for NOT NULL column
                    'description' => $backlogTask->description ?? '',
                    'feedback_information' => $backlogTask->feedback_information,
                    'feedback_owner_name' => $backlogTask->feedback_owner_name,
                    'feedback_emails' => $backlogTask->feedback_emails,
                    'location_id' => $backlogTask->location_id,
                    'priority' => $backlogTask->priority,
                    'estimated_time_minutes' => $backlogTask->estimated_time_minutes,
                ]);
            }
        }
    }

    /**
     * @param array{
     *   selected_default_tasks?: array<int,int>,
     *   selected_backlog_tasks?: array<int,int>,
     *   location_ids?: array<int,int>
     * } $validatedData
     */
    private function updatePlanningTasks(Planning $planning, array $validatedData): void
    {
        // Ensure vehicle tasks are present for assigned vehicle (if any were added after planning creation)
        if ($planning->vehicle_id) {
            $existingLinkedVehicleTaskIds = $planning->planningTasks()
                ->where('is_vehicle_task', true)
                ->whereNotNull('vehicle_task_id')
                ->pluck('vehicle_task_id');

            $openVehicleTasks = \App\Models\VehicleTask::where('vehicle_id', $planning->vehicle_id)
                ->where('status', \App\Enums\TaskStatus::OPEN->value)
                ->whereNotIn('id', $existingLinkedVehicleTaskIds)
                ->orderBy('created_at')
                ->get();

            foreach ($openVehicleTasks as $vt) {
                $planning->planningTasks()->create([
                    'vehicle_task_id' => $vt->id,
                    'title' => $vt->title,
                    'description' => $vt->description,
                    'status' => \App\Enums\TaskStatus::OPEN,
                    'estimated_time_minutes' => $vt->estimated_time_minutes,
                    'is_vehicle_task' => true,
                ]);
            }
        }

        // Logic for adding/removing default tasks based on selection
        $current_default_planning_tasks = $planning->planningTasks()
            ->whereNotNull('default_task_id')
            ->get()
            ->keyBy(fn($pt) => $pt->location_id . '-' . $pt->default_task_id);

        $desired_default_task_state = collect();
        if (!empty($validatedData['selected_default_tasks']) && !empty($validatedData['location_ids'])) {
            /** @var array<int,int> $locIds2 */
            $locIds2 = array_map('intval', $validatedData['location_ids']);
            $selected_location_ids_for_planning = collect($locIds2);
            /** @var array<int,int> $defaultIds2 */
            $defaultIds2 = array_map('intval', $validatedData['selected_default_tasks']);
            $default_task_templates = DefaultTask::with('locations')->findMany($defaultIds2);
            $locations = Location::findMany($selected_location_ids_for_planning);

            foreach ($selected_location_ids_for_planning as $location_id_for_planning) {
                $location = $locations->firstWhere('id', $location_id_for_planning);
                if (!$location) continue;

                foreach ($default_task_templates as $default_task_template) {
                    if ($default_task_template->locations->contains('id', $location_id_for_planning)) {
                        $estimatedTime = $default_task_template->calculateEstimatedTime($location);
                        $desired_default_task_state->put($location_id_for_planning . '-' . $default_task_template->id, [
                            'location_id' => $location_id_for_planning,
                            'default_task_id' => $default_task_template->id,
                            'title' => $default_task_template->title,
                            'description' => $default_task_template->description,
                            'feedback_information' => $default_task_template->feedback_information,
                            'feedback_owner_name' => $default_task_template->feedback_owner_name,
                            'feedback_emails' => $default_task_template->feedback_emails,
                            'estimated_time_minutes' => $estimatedTime,
                        ]);
                    }
                }
            }
        }

        $task_ids_to_delete = $current_default_planning_tasks->diffKeys($desired_default_task_state)->pluck('id');
        if ($task_ids_to_delete->isNotEmpty()) {
            $planning->planningTasks()->whereIn('id', $task_ids_to_delete)->delete();
        }

        $tasks_to_add_data = $desired_default_task_state->diffKeys($current_default_planning_tasks);
        foreach ($tasks_to_add_data as $data) {
            $template = DefaultTask::find($data['default_task_id']);
            if ($template) {
                // Duplicate DefaultTask to a normal Task
                $newTask = Task::create([
                    'location_id' => $data['location_id'],
                    'title' => $template->title,
                    'description' => $template->description ?? '',
                    'feedback_information' => $template->feedback_information,
                    'feedback_owner_name' => $template->feedback_owner_name,
                    'feedback_emails' => $template->feedback_emails,
                    'estimated_time_minutes' => $data['estimated_time_minutes'],
                    'status' => TaskStatus::OPEN,
                    'priority' => TaskPriority::NORMAL,
                    'end_day_action_title' => $template->end_day_action_title,
                    'end_day_action_description' => $template->end_day_action_description,
                    'created_by' => Auth::id(),
                ]);

                // Sync requirements from template to new task
                if ($template->requirements()->exists()) {
                    $newTask->requirements()->sync($template->requirements->pluck('id'));
                }

                $planning->planningTasks()->create([
                    'location_id' => $data['location_id'],
                    'task_id' => $newTask->id,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? '',
                    'feedback_information' => $data['feedback_information'],
                    'feedback_owner_name' => $data['feedback_owner_name'] ?? null,
                    'feedback_emails' => $data['feedback_emails'] ?? null,
                    'estimated_time_minutes' => $data['estimated_time_minutes'],
                ]);
            }
        }

        // Logic for adding/removing backlog tasks
        $selected_backlog_task_ids_input = $validatedData['selected_backlog_tasks'] ?? [];
        /** @var array<int,int|string> $selected_backlog_task_ids_input */
        $selected_backlog_task_ids = collect($selected_backlog_task_ids_input)->map(fn($id) => (int)$id);
        $current_planning_tasks_from_backlog = $planning->planningTasks()
            ->whereNotNull('task_id')
            ->whereNull('default_task_id')
            ->whereNull('vehicle_task_id')
            ->get();

        $backlog_task_ids_to_delete_from_planning = $current_planning_tasks_from_backlog
            ->filter(fn($pt) => !$selected_backlog_task_ids->contains($pt->task_id))
            ->pluck('id');
        if ($backlog_task_ids_to_delete_from_planning->isNotEmpty()) {
            $planning->planningTasks()->whereIn('id', $backlog_task_ids_to_delete_from_planning)->delete();
        }

        $current_linked_backlog_task_ids = $current_planning_tasks_from_backlog->pluck('task_id');
        $new_backlog_task_ids_to_add = $selected_backlog_task_ids->diff($current_linked_backlog_task_ids);
        if ($new_backlog_task_ids_to_add->isNotEmpty()) {
            $backlogTasksToAdd = Task::query()
                ->whereIn('id', $new_backlog_task_ids_to_add)
                ->get();
            foreach ($backlogTasksToAdd as $backlogTask) {
                $planning->planningTasks()->create([
                    'task_id' => $backlogTask->id,
                    'title' => $backlogTask->title,
                    'description' => $backlogTask->description,
                    'feedback_information' => $backlogTask->feedback_information,
                    'feedback_owner_name' => $backlogTask->feedback_owner_name,
                    'feedback_emails' => $backlogTask->feedback_emails,
                    'location_id' => $backlogTask->location_id,
                    'priority' => $backlogTask->priority,
                    'estimated_time_minutes' => $backlogTask->estimated_time_minutes,
                ]);
            }
        }
    }
}
