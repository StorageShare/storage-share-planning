<?php

namespace App\Http\Controllers;

use App\Models\Planning;
use App\Models\Location;
use App\Models\DefaultTask;
use App\Models\Task;
use App\Http\Requests\StorePlanningRequest;
use App\Http\Requests\UpdatePlanningRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB; // For database transactions
use App\Enums\TaskPriority; // Importeer de Enum
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class PlanningController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $sortBy = $request->input('sort_by', 'planned_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $searchTerm = $request->input('search_term', '');
        $activeFilter = $request->input('filter'); // For status filtering

        $query = Planning::with('locations')->withCount('planningTasks');

        $user = Auth::user();
        if ($user && $user->role !== \App\Enums\Role::ADMIN) {
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
            return [$location->id => $location->defaultTasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'estimated_time_minutes' => $task->estimated_time_minutes ?? 0
                ];
            })];
        });

        // Haal alle backlog taken op, gegroepeerd per locatie_id
        $all_backlog_tasks = Task::whereIn('status', ['open', 'in_progress'])
            // ->whereDoesntHave('planningTasks') // Alleen taken die nog niet in *enige* planning zitten
            // Toon alle open/in_progress taken, de create form logica zal bepalen welke al in *deze* (nieuwe) planning zitten.
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $backlogTasksByLocation = $all_backlog_tasks->groupBy('location_id')
            ->map(function ($tasks) {
                return $tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => $task->priority,
                        'estimated_time_minutes' => $task->estimated_time_minutes ?? 0
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
                $location->name                               // Ascending name
            ];
        })->values();

        $selected_location_id = $request->query('location_id');
        
        return view('plannings.create', compact(
            'locations',
            'defaultTasksByLocation',
            'backlogTasksByLocation',
            'backlogPriorityCountsByLocation',
            'backlogTotalEstimatedTimeByLocation',
            'selected_location_id',
            'users'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlanningRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();

        try {
            DB::beginTransaction();

            $planning = Planning::create([
                'planned_date' => $validatedData['planned_date'],
                'notes' => $validatedData['notes'] ?? null,
                'status' => 'open', // Default status for new plannings
                'created_by' => auth()->id(),
            ]);

            if (!empty($validatedData['location_ids'])) {
                $planning->locations()->sync($validatedData['location_ids']);
            }

            if (!empty($validatedData['user_ids'])) {
                $planning->users()->sync($validatedData['user_ids']);
            }

            // Detach all existing default task derived planning tasks first to handle updates/removals cleanly.
            // This is simpler than trying to diff for the store method; update will need more complex diffing.
            // For store, we assume a fresh set of tasks for the given locations.
            // $planning->planningTasks()->whereNotNull('default_task_id')->delete(); // Revisit if this is too aggressive

            if (!empty($validatedData['selected_default_tasks']) && !empty($validatedData['location_ids'])) {
                $selected_location_ids = collect($validatedData['location_ids']);
                // Load default tasks with their general applicable locations
                $default_task_templates = DefaultTask::with('locations')->findMany($validatedData['selected_default_tasks']);

                foreach ($selected_location_ids as $location_id_for_planning) {
                    foreach ($default_task_templates as $default_task_template) {
                        // Check if this default task template is generally applicable to this location
                        if ($default_task_template->locations->contains($location_id_for_planning)) {
                            $planning->planningTasks()->create([
                                'default_task_id' => $default_task_template->id,
                                'location_id'     => $location_id_for_planning, // Assign specific location_id
                                'title'           => $default_task_template->title,
                                'description'     => $default_task_template->description,
                            ]);
                        }
                    }
                }
            }

            if (!empty($validatedData['selected_backlog_tasks'])) {
                $backlogTasks = Task::findMany($validatedData['selected_backlog_tasks']);
                foreach ($backlogTasks as $backlogTask) {
                    $planning->planningTasks()->create([
                        'task_id' => $backlogTask->id,
                        'title' => $backlogTask->title,
                        'description' => $backlogTask->description,
                        // Prioriteit en andere details van Task kunnen hier worden overgenomen indien nodig
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('plannings.show', $planning)->with('success', 'Planning succesvol aangemaakt.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating planning: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->back()->withInput()->with('error', 'Fout bij het aanmaken van de planning: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Planning $planning): View
    {
        $planning->load([
            'locations', 
            'planningTasks.task.location', 
            'planningTasks.defaultTask.locations', // Still useful to know general applicability of the template
            'planningTasks.specificLocation' // For the specific instance
        ]);
        return view('plannings.show', compact('planning'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Planning $planning): View
    {
        $users = User::all();
        $locations = Location::with('defaultTasks')->orderBy('name')->get();
        $defaultTasksByLocation = $locations->mapWithKeys(function ($location) {
            return [$location->id => $location->defaultTasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'estimated_time_minutes' => $task->estimated_time_minutes ?? 0
                ];
            })];
        });

        $planning->load('locations'); 
        $current_selected_location_ids = $planning->locations->pluck('id')->all();

        $all_relevant_backlog_tasks = Task::whereIn('status', ['open', 'in_progress'])
            ->where(function ($query) use ($planning) {
                $query->whereDoesntHave('planningTasks')
                      ->orWhereHas('planningTasks', function ($subQuery) use ($planning) {
                          $subQuery->where('planning_id', $planning->id);
                      });
            })
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $backlogTasksByLocation = $all_relevant_backlog_tasks->groupBy('location_id')
            ->map(function ($tasks) {
                return $tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => $task->priority,
                        'estimated_time_minutes' => $task->estimated_time_minutes ?? 0
                    ];
                });
            });

        $backlogPriorityCountsByLocation = $all_relevant_backlog_tasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                return [
                    TaskPriority::HIGH->value => $tasks_for_location->where('priority', TaskPriority::HIGH)->count(),
                    TaskPriority::NORMAL->value => $tasks_for_location->where('priority', TaskPriority::NORMAL)->count(),
                    TaskPriority::LOW->value => $tasks_for_location->where('priority', TaskPriority::LOW)->count(),
                ];
            });

        $backlogTotalEstimatedTimeByLocation = $all_relevant_backlog_tasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                // Assuming 'estimated_time_minutes' is the field for estimated time.
                return $tasks_for_location->sum('estimated_time_minutes');
            });
        
        $current_selected_default_tasks = $planning->planningTasks
            ->whereNotNull('default_task_id')
            ->pluck('default_task_id')
            ->map(fn ($id) => (string)$id)
            ->all();

        $current_selected_backlog_tasks = $planning->planningTasks
            ->whereNotNull('task_id')
            ->pluck('task_id')
            ->map(fn ($id) => (string)$id)
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
                $location->name                               // Ascending name
            ];
        })->values();

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
            'users'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlanningRequest $request, Planning $planning): RedirectResponse
    {
        $validatedData = $request->validated();

        try {
            DB::beginTransaction();

            $planning->update([
                'planned_date' => $validatedData['planned_date'],
                'notes' => $validatedData['notes'] ?? null,
                // Status update could be handled here if part of the form
                // 'status' => $validatedData['status'] ?? $planning->status,
            ]);

            if (isset($validatedData['location_ids'])) {
                $planning->locations()->sync($validatedData['location_ids']);
            }

            if (isset($validatedData['user_ids'])) {
                $planning->users()->sync($validatedData['user_ids']);
            } else {
                $planning->users()->sync([]);
            }

            // --- Handle Default Tasks ---
            $current_default_planning_tasks = $planning->planningTasks()
                ->whereNotNull('default_task_id')
                ->get()
                ->keyBy(fn($pt) => $pt->location_id . '-' . $pt->default_task_id); // Key by composite for easy lookup

            $desired_default_task_state = collect();
            if (!empty($validatedData['selected_default_tasks']) && !empty($validatedData['location_ids'])) {
                $selected_location_ids_for_planning = collect($validatedData['location_ids']);
                $default_task_templates = DefaultTask::with('locations')->findMany($validatedData['selected_default_tasks']);

                foreach ($selected_location_ids_for_planning as $location_id_for_planning) {
                    foreach ($default_task_templates as $default_task_template) {
                        if ($default_task_template->locations->contains($location_id_for_planning)) {
                            $desired_default_task_state->put($location_id_for_planning . '-' . $default_task_template->id, [
                                'location_id' => $location_id_for_planning,
                                'default_task_id' => $default_task_template->id,
                                'title' => $default_task_template->title,
                                'description' => $default_task_template->description,
                            ]);
                        }
                    }
                }
            }

            // Tasks to delete: those in current but not in desired
            $task_ids_to_delete = $current_default_planning_tasks
                ->diffKeys($desired_default_task_state)
                ->pluck('id');

            if ($task_ids_to_delete->isNotEmpty()) {
                $planning->planningTasks()->whereIn('id', $task_ids_to_delete)->delete();
            }

            // Tasks to add: those in desired but not in current
            $tasks_to_add_data = $desired_default_task_state->diffKeys($current_default_planning_tasks);

            foreach ($tasks_to_add_data as $data) {
                $planning->planningTasks()->create([
                    'planning_id'     => $planning->id, // Not strictly needed if using relationship, but good for clarity
                    'location_id'     => $data['location_id'],
                    'default_task_id' => $data['default_task_id'],
                    'title'           => $data['title'],
                    'description'     => $data['description'],
                ]);
            }

            // --- Handle Backlog Tasks (largely unchanged but ensure consistency if needed) ---
            $selected_backlog_task_ids = collect($validatedData['selected_backlog_tasks'] ?? [])->map(fn($id) => (int)$id);
            $current_planning_tasks_from_backlog = $planning->planningTasks()->whereNotNull('task_id')->get();

            $backlog_task_ids_to_delete_from_planning = $current_planning_tasks_from_backlog
                ->filter(fn($pt) => !$selected_backlog_task_ids->contains($pt->task_id))
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
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('plannings.show', $planning)->with('success', 'Planning succesvol bijgewerkt.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating planning: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->back()->withInput()->with('error', 'Fout bij het bijwerken van de planning: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Planning $planning): RedirectResponse
    {
        $planning->delete();
        return redirect()->route('plannings.index')->with('success', 'Planning succesvol verwijderd.');
    }

    // --- Extra methodes voor PlanningTasks --- (Voorbeeld)

    /**
     * Markeer een planningstaak als voltooid.
     */
    // public function completePlanningTask(Planning $planning, PlanningTask $planningTask, Request $request): RedirectResponse
    // {
    //     if ($planningTask->planning_id !== $planning->id) {
    //         abort(403);
    //     }
    //     $planningTask->update([
    //         'completed_at' => now(),
    //         'completed_notes' => $request->input('completed_notes'),
    //     ]);
    //     // Optioneel: foto uploaden en koppelen aan $planningTask
    //     return redirect()->route('plannings.show', $planning)->with('success', 'Taak \'' . $planningTask->title . '\' voltooid.');
    // }
}
