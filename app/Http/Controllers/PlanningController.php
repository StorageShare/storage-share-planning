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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request; // For database transactions
use Illuminate\Support\Facades\Auth; // Importeer de Enum
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __construct(
        private $travelTimeService = null
    ) {
        // Allow container-bound mocks (including anonymous classes) to be injected in tests
        $this->travelTimeService = $travelTimeService ?: app(TravelTimeService::class);
    }

    /**
     * Overview of plannings that have tasks pending review.
     */
    public function review(Request $request): View
    {
        $plannings = Planning::with(['locations', 'users'])
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
            ->orderByDesc('planned_date')
            ->paginate(15)
            ->withQueryString();

        return view('plannings.review', compact('plannings'));
    }

    /**
     * Update actual on-location time (HH:mm) for a given location in this planning.
     */
    public function updateLocationActualTime(Request $request, Planning $planning, Location $location)
    {
        $request->validate([
            'time' => ['required','regex:/^\d{1,2}:\d{2}$/'],
        ]);

        $seconds = $this->parseHHMMToSeconds($request->string('time'));

        // Set the total on-location time directly to the provided HH:mm (interpreted as total desired time on location)
        $timer = \App\Models\PlanningLocationTimer::firstOrNew([
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
    public function updateTravelToTime(Request $request, Planning $planning, Location $location)
    {
        $request->validate([
            'time' => ['required','regex:/^\d{1,2}:\d{2}$/'],
        ]);

        $seconds = $this->parseHHMMToSeconds($request->string('time'));

        $timer = \App\Models\PlanningLocationTimer::firstOrNew([
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
    public function updateTravelBackTime(Request $request, Planning $planning)
    {
        $request->validate([
            'time' => ['required','regex:/^\d{1,2}:\d{2}$/'],
        ]);

        $seconds = $this->parseHHMMToSeconds($request->string('time'));

        $timer = \App\Models\PlanningLocationTimer::firstOrNew([
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
        if ($user && $user->role !== \App\Enums\Role::ADMIN) {
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

        $plannings = $query->paginate(15)->withQueryString();

        return view('plannings.index', compact(
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
        $defaultTasksByLocation = $locations->mapWithKeys(function ($location) {
            // Haal alle default tasks op die specifiek aan deze locatie zijn gekoppeld OF die voor alle locaties gelden
            $locationSpecificTasks = $location->defaultTasks;
            $allLocationTasks = DefaultTask::forAllLocations()->get();

            // Combineer beide collecties en verwijder duplicaten
            $allTasks = $locationSpecificTasks->merge($allLocationTasks)->unique('id');

            return [$location->id => $allTasks->map(function ($task) use ($location) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'estimated_time_minutes' => $task->calculateEstimatedTime($location),
                    'applies_to_all_locations' => $task->applies_to_all_locations ?? false,
                    'is_always_included' => $task->is_always_included ?? false,
                ];
            })];
        });

        // Haal alle backlog taken op die beschikbaar zijn voor planning.
        $all_backlog_tasks = Task::query()
            ->where(function ($query) {
                $query->whereIn('status', ['open', 'in_progress', 'rejected'])
                      ->whereDoesntHave('planningTasks');
            })
            ->orderByRaw('deadline IS NULL ASC, deadline ASC') // Eerst taken met deadline (eerste deadline eerst)
            ->orderByRaw('CASE status WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC', [
                'open', 'in_progress', 'rejected'
            ]) // Daarna op status: open, in_progress, rejected
            ->orderBy('priority', 'asc') // Als tie-breaker: priority
            ->orderBy('created_at', 'asc') // Als laatste tie-breaker: created_at
            ->get();

        $backlogTasksByLocation = $all_backlog_tasks->groupBy('location_id')
            ->map(function ($tasks) {
                return $tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => [
                            'value' => $task->priority->value,
                            'label' => $task->priority->label(),
                        ],
                        'status' => $task->status ?: \App\Enums\TaskStatus::OPEN,
                        'deadline' => $task->deadline,
                        'estimated_time_minutes' => $task->estimated_time_minutes ?? 0,
                    ];
                });
            });

        $backlogPriorityCountsByLocation = $all_backlog_tasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                return [
                    TaskPriority::HIGH->value => $tasks_for_location->where('priority', TaskPriority::HIGH)->count(),
                    TaskPriority::NORMAL->value => $tasks_for_location->where('priority', TaskPriority::NORMAL)->count(),
                    TaskPriority::LOW->value => $tasks_for_location->where('priority', TaskPriority::LOW)->count(),
                ];
            });

        $backlogTotalEstimatedTimeByLocation = $all_backlog_tasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                // Assuming 'estimated_time_minutes' is the field for estimated time.
                return $tasks_for_location->sum('estimated_time_minutes');
            });

        // Sort locations based on priority task counts and then name
        $locations = $locations->sortBy(function ($location) use ($backlogPriorityCountsByLocation) {
            $counts = $backlogPriorityCountsByLocation[$location->id] ?? [
                TaskPriority::HIGH->value => 0,
                TaskPriority::NORMAL->value => 0,
                TaskPriority::LOW->value => 0,
            ];

            return [
                -($counts[TaskPriority::HIGH->value] ?? 0),     // Descending high priority
                -($counts[TaskPriority::NORMAL->value] ?? 0),  // Descending normal priority
                -($counts[TaskPriority::LOW->value] ?? 0),     // Descending low priority
                $location->name,                               // Ascending name
            ];
        })->values();

        $selected_location_id = $request->query('location_id');

        $requirements = \App\Models\Requirement::orderBy('name')->get();

        $plannedBacklogTasks = \App\Models\PlanningTask::whereNotNull('task_id')
            ->with('planning:id,planned_date')
            ->get()
            ->mapWithKeys(function ($planningTask) {
                return [$planningTask->task_id => [
                    'planning_id' => $planningTask->planning->id,
                    'planning_title' => $planningTask->planning->planned_date->format('d-m-Y'),
                ]];
            });

        // Vehicles available for selected/planned date (default: today)
        $selectedDate = $request->input('planned_date') ?? now()->toDateString();
        $availableVehicles = Vehicle::query()
            ->whereDoesntHave('plannings', function ($q) use ($selectedDate) {
                // Exclude only vehicles that are assigned to a non-completed planning on the selected date
                // Treat NULL status as non-completed
                $q->whereDate('planned_date', $selectedDate)
                  ->where(function ($qq) {
                      $qq->whereNull('status')
                         ->orWhere('status', '!=', 'completed');
                  });
            })
            ->orderBy('name')
            ->get();

        return view('plannings.create', compact(
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
            if (! empty($validated['user_ids'])) {
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
            ->whereIn('location_type', ['travel','travel_back'])
            ->sum('total_duration_seconds');
        $actualOnLocationSeconds = $planning->locationTimers
            ->where('location_type', 'location')
            ->sum('total_duration_seconds');

        $actualTotals = [
            'travel_seconds' => (int)$actualTravelSeconds,
            'on_location_seconds' => (int)$actualOnLocationSeconds,
        ];

        return view('plannings.show', compact('planning', 'travelTimes', 'timeOverview', 'onLocationTimers', 'travelToTimers', 'travelBackTimer', 'actualTotals'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Planning $planning): View
    {
        $users = User::all();
        $locations = Location::with('defaultTasks')->orderBy('name')->get();
        $defaultTasksByLocation = $locations->mapWithKeys(function ($location) {
            // Haal alle default tasks op die specifiek aan deze locatie zijn gekoppeld OF die voor alle locaties gelden
            $locationSpecificTasks = $location->defaultTasks;
            $allLocationTasks = DefaultTask::forAllLocations()->get();

            // Combineer beide collecties en verwijder duplicaten
            $allTasks = $locationSpecificTasks->merge($allLocationTasks)->unique('id');

            return [$location->id => $allTasks->map(function ($task) use ($location) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'estimated_time_minutes' => $task->calculateEstimatedTime($location),
                    'applies_to_all_locations' => $task->applies_to_all_locations ?? false,
                    'is_always_included' => $task->is_always_included ?? false,
                ];
            })];
        });

        $planning->load('locations');
        $current_selected_location_ids = $planning->locations->pluck('id')->all();

        // Haal alle backlog taken op die beschikbaar zijn voor planning.
        $availableBacklogTasks = Task::query()
            ->where(function ($query) use ($planning) {
                // Taak is 'open', 'in_progress', of 'rejected' EN is nog niet aan een planning gekoppeld
                $query->whereIn('status', ['open', 'in_progress', 'rejected'])
                      ->whereDoesntHave('planningTasks');
            })
            ->orWhereHas('planningTasks', function ($query) use ($planning) {
                // OF de taak is gekoppeld aan de HUIDIGE planning
                $query->where('planning_id', $planning->id);
            })
            ->orderByRaw('deadline IS NULL ASC, deadline ASC') // Eerst taken met deadline (eerste deadline eerst)
            ->orderByRaw('CASE status WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC', [
                'open', 'in_progress', 'rejected'
            ]) // Daarna op status: open, in_progress, rejected
            ->orderBy('priority', 'asc') // Als tie-breaker: priority
            ->orderBy('created_at', 'asc') // Als laatste tie-breaker: created_at
            ->get();

        $backlogTasksByLocation = $availableBacklogTasks->groupBy('location_id')
            ->map(function ($tasks) {
                return $tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => [
                            'value' => $task->priority->value,
                            'label' => $task->priority->label(),
                        ],
                        'status' => $task->status ?: \App\Enums\TaskStatus::OPEN,
                        'deadline' => $task->deadline,
                        'estimated_time_minutes' => $task->estimated_time_minutes ?? 0,
                    ];
                });
            });

        $backlogPriorityCountsByLocation = $availableBacklogTasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                return [
                    TaskPriority::HIGH->value => $tasks_for_location->where('priority', TaskPriority::HIGH)->count(),
                    TaskPriority::NORMAL->value => $tasks_for_location->where('priority', TaskPriority::NORMAL)->count(),
                    TaskPriority::LOW->value => $tasks_for_location->where('priority', TaskPriority::LOW)->count(),
                ];
            });

        $backlogTotalEstimatedTimeByLocation = $availableBacklogTasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                // Assuming 'estimated_time_minutes' is the field for estimated time.
                return $tasks_for_location->sum('estimated_time_minutes');
            });

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
        $locations = $locations->sortBy(function ($location) use ($backlogPriorityCountsByLocation) {
            $counts = $backlogPriorityCountsByLocation[$location->id] ?? [
                TaskPriority::HIGH->value => 0,
                TaskPriority::NORMAL->value => 0,
                TaskPriority::LOW->value => 0,
            ];

            return [
                -($counts[TaskPriority::HIGH->value] ?? 0),     // Descending high priority
                -($counts[TaskPriority::NORMAL->value] ?? 0),  // Descending normal priority
                -($counts[TaskPriority::LOW->value] ?? 0),     // Descending low priority
                $location->name,                               // Ascending name
            ];
        })->values();

        $plannedBacklogTasks = \App\Models\PlanningTask::whereNotNull('task_id')
            ->where('planning_id', '!=', $planning->id)
            ->with('planning:id,planned_date')
            ->get()
            ->mapWithKeys(function ($planningTask) {
                return [$planningTask->task_id => [
                    'planning_id' => $planningTask->planning->id,
                    'planning_title' => $planningTask->planning->planned_date->format('d-m-Y'),
                ]];
            });

        // Vehicles available for the planning date; include currently assigned vehicle if set
        $selectedDate = $planning->planned_date->toDateString();
        $availableVehicles = Vehicle::query()
            ->whereDoesntHave('plannings', function ($q) use ($selectedDate, $planning) {
                // Exclude only vehicles that are assigned to a non-completed planning on the selected date, excluding the current planning
                // Treat NULL status as non-completed
                $q->whereDate('planned_date', $selectedDate)
                  ->where('plannings.id', '!=', $planning->id)
                  ->where(function ($qq) {
                      $qq->whereNull('status')
                         ->orWhere('status', '!=', 'completed');
                  });
            })
            ->orderBy('name')
            ->get();
        if ($planning->vehicle && !$availableVehicles->contains('id', $planning->vehicle->id)) {
            $availableVehicles->push($planning->vehicle);
            $availableVehicles = $availableVehicles->sortBy('name')->values();
        }

        $requirements = \App\Models\Requirement::orderBy('name')->get();

        return view('plannings.edit', compact(
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
     * Get timer data for a specific location in a planning.
     */
    public function getLocationTimer(Planning $planning, $locationId)
    {
        // Determine location type and actual location ID
        $actualLocationId = null;
        $locationType = 'location';

        if ($locationId === 'backlog') {
            $actualLocationId = null;
            $locationType = 'backlog';
        } elseif (str_starts_with($locationId, 'travel_to_')) {
            // Travel timer - extract destination location ID
            $actualLocationId = str_replace('travel_to_', '', $locationId);
            $locationType = 'travel';
        } elseif ($locationId === 'travel_back') {
            // Return travel timer back to start location
            $actualLocationId = null;
            $locationType = 'travel_back';
        } else {
            // Regular location
            $actualLocationId = $locationId;
            $locationType = 'location';
        }

        $timer = PlanningLocationTimer::where('planning_id', $planning->id)
            ->where('location_id', $actualLocationId)
            ->where('location_type', $locationType)
            ->first();

        if (!$timer) {
            return response()->json([
                'started_at' => null,
                'ended_at' => null,
                'total_duration' => 0,
            ]);
        }

        return response()->json([
            'started_at' => $timer->started_at?->toISOString(),
            'ended_at' => $timer->ended_at?->toISOString(),
            'total_duration' => $timer->total_duration_seconds,
        ]);
    }

    /**
     * Start timer for a specific location in a planning.
     */
    public function startLocationTimer(Planning $planning, $locationId)
    {
        // Determine location type and actual location ID
        $actualLocationId = null;
        $locationType = 'location';

        if ($locationId === 'backlog') {
            $actualLocationId = null;
            $locationType = 'backlog';
        } elseif (str_starts_with($locationId, 'travel_to_')) {
            // Travel timer - extract destination location ID
            $actualLocationId = str_replace('travel_to_', '', $locationId);
            $locationType = 'travel';
        } elseif ($locationId === 'travel_back') {
            // Return travel timer back to start location
            $actualLocationId = null;
            $locationType = 'travel_back';
        } else {
            // Regular location
            $actualLocationId = $locationId;
            $locationType = 'location';
        }

        $timer = PlanningLocationTimer::where('planning_id', $planning->id)
            ->where('location_id', $actualLocationId)
            ->where('location_type', $locationType)
            ->first();

        if ($timer) {
            // Timer exists - just update start time and clear end time
            $timer->update([
                'started_at' => now(),
                'ended_at' => null,
            ]);
        } else {
            // Create new timer
            $timer = PlanningLocationTimer::create([
                'planning_id' => $planning->id,
                'location_id' => $actualLocationId,
                'location_type' => $locationType,
                'started_at' => now(),
                'ended_at' => null,
                'total_duration_seconds' => 0,
            ]);
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
    public function stopLocationTimer(Request $request, Planning $planning, $locationId)
    {
        $request->validate([
            'total_duration' => 'required|integer|min:0',
        ]);

        // Determine location type and actual location ID
        $actualLocationId = null;
        $locationType = 'location';

        if ($locationId === 'backlog') {
            $actualLocationId = null;
            $locationType = 'backlog';
        } elseif (str_starts_with($locationId, 'travel_to_')) {
            // Travel timer - extract destination location ID
            $actualLocationId = str_replace('travel_to_', '', $locationId);
            $locationType = 'travel';
        } elseif ($locationId === 'travel_back') {
            // Return travel timer back to start location
            $actualLocationId = null;
            $locationType = 'travel_back';
        } else {
            // Regular location
            $actualLocationId = $locationId;
            $locationType = 'location';
        }

        $timer = PlanningLocationTimer::where('planning_id', $planning->id)
            ->where('location_id', $actualLocationId)
            ->where('location_type', $locationType)
            ->first();

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
    public function restartLocationTimer(Request $request, Planning $planning, $locationId)
    {
        $request->validate([
            'previous_duration' => 'required|integer|min:0',
        ]);

        // Determine location type and actual location ID
        $actualLocationId = null;
        $locationType = 'location';

        if ($locationId === 'backlog') {
            $actualLocationId = null;
            $locationType = 'backlog';
        } elseif (str_starts_with($locationId, 'travel_to_')) {
            // Travel timer - extract destination location ID
            $actualLocationId = str_replace('travel_to_', '', $locationId);
            $locationType = 'travel';
        } elseif ($locationId === 'travel_back') {
            // Return travel timer back to start location
            $actualLocationId = null;
            $locationType = 'travel_back';
        } else {
            // Regular location
            $actualLocationId = $locationId;
            $locationType = 'location';
        }

        $timer = PlanningLocationTimer::where('planning_id', $planning->id)
            ->where('location_id', $actualLocationId)
            ->where('location_type', $locationType)
            ->first();

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

    private function syncLocations(Planning $planning, array $locationIds, ?string $locationOrder): void
    {
        $orderedIds = $locationOrder ? explode(',', $locationOrder) : [];
        $locationsToSync = [];

        // Ensure all selected locations are in the ordered list to avoid data loss
        $finalOrderedIds = collect($orderedIds)->unique()->filter(fn ($id) => in_array($id, $locationIds));
        foreach ($locationIds as $id) {
            if (! $finalOrderedIds->contains($id)) {
                $finalOrderedIds->push($id);
            }
        }

        foreach ($finalOrderedIds as $index => $locationId) {
            $locationsToSync[$locationId] = ['sort_order' => $index];
        }

        $planning->locations()->sync($locationsToSync);
    }

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

        if (! empty($validatedData['selected_default_tasks']) && ! empty($validatedData['location_ids'])) {
            $selected_location_ids = collect($validatedData['location_ids']);
            $default_task_templates = DefaultTask::with('locations')->findMany($validatedData['selected_default_tasks']);
            $locations = Location::findMany($selected_location_ids);

            foreach ($selected_location_ids as $location_id) {
                $location = $locations->firstWhere('id', $location_id);
                if (!$location) continue;

                foreach ($default_task_templates as $template) {
                    if ($template->locations->contains($location_id)) {
                        $estimatedTime = $template->calculateEstimatedTime($location);

                        // Duplicate DefaultTask to a normal Task
                        $newTask = Task::create([
                            'location_id' => $location_id,
                            'title' => $template->title,
                            'description' => $template->description ?? '',
                            'feedback_information' => $template->feedback_information,
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
                            'default_task_id' => $template->id,
                            'title' => $template->title,
                            // Ensure non-null description for NOT NULL column
                            'description' => $template->description ?? '',
                            'feedback_information' => $template->feedback_information,
                            'estimated_time_minutes' => $estimatedTime,
                        ]);
                    }
                }
            }
        }

        // Logic for adding backlog tasks
        if (! empty($validatedData['selected_backlog_tasks'])) {
            $backlogTasks = Task::findMany($validatedData['selected_backlog_tasks']);
            foreach ($backlogTasks as $backlogTask) {
                $planning->planningTasks()->create([
                    'task_id' => $backlogTask->id,
                    'title' => $backlogTask->title,
                    // Ensure non-null description for NOT NULL column
                    'description' => $backlogTask->description ?? '',
                    'feedback_information' => $backlogTask->feedback_information,
                    'location_id' => $backlogTask->location_id,
                    'priority' => $backlogTask->priority,
                    'estimated_time_minutes' => $backlogTask->estimated_time_minutes,
                ]);
            }
        }
    }

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
            ->keyBy(fn ($pt) => $pt->location_id.'-'.$pt->default_task_id);

        $desired_default_task_state = collect();
        if (! empty($validatedData['selected_default_tasks']) && ! empty($validatedData['location_ids'])) {
            $selected_location_ids_for_planning = collect($validatedData['location_ids']);
            $default_task_templates = DefaultTask::with('locations')->findMany($validatedData['selected_default_tasks']);
            $locations = Location::findMany($selected_location_ids_for_planning);

            foreach ($selected_location_ids_for_planning as $location_id_for_planning) {
                $location = $locations->firstWhere('id', $location_id_for_planning);
                if (!$location) continue;

                foreach ($default_task_templates as $default_task_template) {
                    if ($default_task_template->locations->contains($location_id_for_planning)) {
                        $estimatedTime = $default_task_template->calculateEstimatedTime($location);
                        $desired_default_task_state->put($location_id_for_planning.'-'.$default_task_template->id, [
                            'location_id' => $location_id_for_planning,
                            'default_task_id' => $default_task_template->id,
                            'title' => $default_task_template->title,
                            'description' => $default_task_template->description,
                            'feedback_information' => $default_task_template->feedback_information,
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
                    'estimated_time_minutes' => $data['estimated_time_minutes'],
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

                $data['task_id'] = $newTask->id;
            }
            $planning->planningTasks()->create($data);
        }

        // Logic for adding/removing backlog tasks
        $selected_backlog_task_ids = collect($validatedData['selected_backlog_tasks'] ?? [])->map(fn ($id) => (int) $id);
        $current_planning_tasks_from_backlog = $planning->planningTasks()->whereNotNull('task_id')->get();

        $backlog_task_ids_to_delete_from_planning = $current_planning_tasks_from_backlog
            ->filter(fn ($pt) => ! $selected_backlog_task_ids->contains($pt->task_id))
            ->pluck('id');
        if ($backlog_task_ids_to_delete_from_planning->isNotEmpty()) {
            $planning->planningTasks()->whereIn('id', $backlog_task_ids_to_delete_from_planning)->delete();
        }

        $current_linked_backlog_task_ids = $current_planning_tasks_from_backlog->pluck('task_id');
        $new_backlog_task_ids_to_add = $selected_backlog_task_ids->diff($current_linked_backlog_task_ids);
        if ($new_backlog_task_ids_to_add->isNotEmpty()) {
            $backlogTasksToAdd = Task::findMany($new_backlog_task_ids_to_add);
            foreach ($backlogTasksToAdd as $backlogTask) {
                $planning->planningTasks()->create([
                    'task_id' => $backlogTask->id,
                    'title' => $backlogTask->title,
                    'description' => $backlogTask->description,
                    'feedback_information' => $backlogTask->feedback_information,
                    'location_id' => $backlogTask->location_id,
                    'priority' => $backlogTask->priority,
                    'estimated_time_minutes' => $backlogTask->estimated_time_minutes,
                ]);
            }
        }
    }
}
