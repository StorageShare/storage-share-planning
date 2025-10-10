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
use App\Services\TravelTimeService;
use App\Mail\PlanningReadyNotificationMail;
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
        private TravelTimeService $travelTimeService
    ) {}

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
            'activeFilter'
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

            return [$location->id => $allTasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'estimated_time_minutes' => $task->estimated_time_minutes ?? 0,
                    'applies_to_all_locations' => $task->applies_to_all_locations ?? false,
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

        $plannedBacklogTasks = \App\Models\PlanningTask::whereNotNull('task_id')
            ->with('planning:id,planned_date')
            ->get()
            ->mapWithKeys(function ($planningTask) {
                return [$planningTask->task_id => [
                    'planning_id' => $planningTask->planning->id,
                    'planning_title' => $planningTask->planning->planned_date->format('d-m-Y'),
                ]];
            });

        return view('plannings.create', compact(
            'locations',
            'defaultTasksByLocation',
            'backlogTasksByLocation',
            'backlogPriorityCountsByLocation',
            'backlogTotalEstimatedTimeByLocation',
            'selected_location_id',
            'users',
            'plannedBacklogTasks'
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
            'planningTasks' => function ($query) {
                $query->with([
                    'task.location',
                    'defaultTask.locations',
                    'specificLocation',
                    'completions' => function ($completionQuery) {
                        $completionQuery->with(['user', 'photos'])->orderBy('created_at', 'desc');
                    },
                ]);
            },
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

        // Create location timers lookup
        $locationTimers = $planning->locationTimers->keyBy(function ($timer) {
            return $timer->location_id ?? 'backlog';
        });

        // Calculate time overview
        $timeOverview = [
            'task_minutes' => $totalTaskMinutes,
            'travel_minutes' => $travelTimes ? $travelTimes['total_duration_minutes'] : 0,
            'total_minutes' => $totalTaskMinutes + ($travelTimes ? $travelTimes['total_duration_minutes'] : 0),
        ];

        return view('plannings.show', compact('planning', 'travelTimes', 'timeOverview', 'locationTimers'));
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

            return [$location->id => $allTasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'estimated_time_minutes' => $task->estimated_time_minutes ?? 0,
                    'applies_to_all_locations' => $task->applies_to_all_locations ?? false,
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
            'plannedBacklogTasks'
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
            // If travelling to the first location of the planning, mark as shared_travel
            $firstLocationId = optional($planning->locations()->orderBy('sort_order')->first())->id;
            if ((string) $actualLocationId === (string) $firstLocationId) {
                $locationType = 'shared_travel';
            } else {
                $locationType = 'travel';
            }
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
            // If travelling to the first location of the planning, mark as shared_travel
            $firstLocationId = optional($planning->locations()->orderBy('sort_order')->first())->id;
            if ((string) $actualLocationId === (string) $firstLocationId) {
                $locationType = 'shared_travel';
            } else {
                $locationType = 'travel';
            }
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
            // If travelling to the first location of the planning, mark as shared_travel
            $firstLocationId = optional($planning->locations()->orderBy('sort_order')->first())->id;
            if ((string) $actualLocationId === (string) $firstLocationId) {
                $locationType = 'shared_travel';
            } else {
                $locationType = 'travel';
            }
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

        // If this was a shared_travel timer, split the travel time across all planning locations
        if ($locationType === 'shared_travel' && $timer->total_duration_seconds > 0) {
            DB::transaction(function () use ($planning, $timer) {
                // Get all locations for this planning (ordered)
                $locations = $planning->locations()->get();
                $count = $locations->count();

                if ($count === 0) {
                    return; // Nothing to split
                }

                $total = (int) $timer->total_duration_seconds;
                $base = intdiv($total, $count);
                $remainder = $total % $count;

                // For idempotency: if travel timer rows already exist for this planning with the same timestamps, skip creation
                // We'll check per location to only create missing ones
                foreach ($locations as $index => $location) {
                    $share = $base + ($index < $remainder ? 1 : 0);

                    // Only create when share > 0 to avoid zero-duration noise
                    if ($share <= 0) {
                        continue;
                    }

                    $existingTimer = PlanningLocationTimer::where('planning_id', $planning->id)
                        ->where('location_id', $location->id)
                        ->where('location_type', 'shared_travel')
                        ->where('started_at', $timer->started_at)
                        ->where('ended_at', $timer->ended_at)
                        ->first();

                    if (!$existingTimer) {
                        PlanningLocationTimer::create([
                            'planning_id' => $planning->id,
                            'location_id' => $location->id,
                            'location_type' => 'shared_travel',
                            'started_at' => $timer->started_at,
                            'ended_at' => $timer->ended_at,
                            'total_duration_seconds' => $share,
                        ]);
                    } else {
                        $existingTimer->update(['total_duration_seconds' => $share]);
                    }
                }
            });
        }

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
            // If travelling to the first location of the planning, mark as shared_travel
            $firstLocationId = optional($planning->locations()->orderBy('sort_order')->first())->id;
            if ((string) $actualLocationId === (string) $firstLocationId) {
                $locationType = 'shared_travel';
            } else {
                $locationType = 'travel';
            }
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
        // Logic for adding default tasks
        if (! empty($validatedData['selected_default_tasks']) && ! empty($validatedData['location_ids'])) {
            $selected_location_ids = collect($validatedData['location_ids']);
            $default_task_templates = DefaultTask::with('locations')->findMany($validatedData['selected_default_tasks']);

            foreach ($selected_location_ids as $location_id) {
                foreach ($default_task_templates as $template) {
                    if ($template->locations->contains($location_id)) {
                        $planning->planningTasks()->create([
                            'location_id' => $location_id,
                            'default_task_id' => $template->id,
                            'title' => $template->title,
                            'description' => $template->description,
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
                    'description' => $backlogTask->description,
                    'location_id' => $backlogTask->location_id,
                    'priority' => $backlogTask->priority,
                    'estimated_time_minutes' => $backlogTask->estimated_time_minutes,
                ]);
            }
        }
    }

    private function updatePlanningTasks(Planning $planning, array $validatedData): void
    {
        // Logic for adding/removing default tasks based on selection
        $current_default_planning_tasks = $planning->planningTasks()
            ->whereNotNull('default_task_id')
            ->get()
            ->keyBy(fn ($pt) => $pt->location_id.'-'.$pt->default_task_id);

        $desired_default_task_state = collect();
        if (! empty($validatedData['selected_default_tasks']) && ! empty($validatedData['location_ids'])) {
            $selected_location_ids_for_planning = collect($validatedData['location_ids']);
            $default_task_templates = DefaultTask::with('locations')->findMany($validatedData['selected_default_tasks']);

            foreach ($selected_location_ids_for_planning as $location_id_for_planning) {
                foreach ($default_task_templates as $default_task_template) {
                    if ($default_task_template->locations->contains($location_id_for_planning)) {
                        $desired_default_task_state->put($location_id_for_planning.'-'.$default_task_template->id, [
                            'location_id' => $location_id_for_planning,
                            'default_task_id' => $default_task_template->id,
                            'title' => $default_task_template->title,
                            'description' => $default_task_template->description,
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
                    'location_id' => $backlogTask->location_id,
                    'priority' => $backlogTask->priority,
                    'estimated_time_minutes' => $backlogTask->estimated_time_minutes,
                ]);
            }
        }
    }
}
