<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\Task;
use Illuminate\Support\Collection;

class PlanningFormDataService
{
    /**
     * @param  Collection<int, Location>  $locations
     * @return Collection<int, Collection<int, array{id:int,title:string,description:string,estimated_time_minutes:int,applies_to_all_locations:bool,is_always_included:bool}>>
     */
    public function buildDefaultTasksByLocation(Collection $locations): Collection
    {
        return $locations->mapWithKeys(function ($location) {
            $locationSpecificTasks = $location->defaultTasks;
            $allLocationTasks = DefaultTask::forAllLocations()->get();
            $allTasks = $locationSpecificTasks->merge($allLocationTasks)->unique('id');

            return [$location->id => $allTasks->map(function ($task) use ($location) {
                return [
                    'id' => (int) $task->id,
                    'title' => (string) $task->title,
                    'description' => (string) $task->description,
                    'estimated_time_minutes' => (int) $task->calculateEstimatedTime($location),
                    'applies_to_all_locations' => (bool) ($task->applies_to_all_locations ?? false),
                    'is_always_included' => (bool) ($task->is_always_included ?? false),
                ];
            })];
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Task>  $tasks
     * @return Collection<int|string, Collection<int|string, array<string, mixed>>>
     */
    public function mapBacklogTasksByLocation(\Illuminate\Database\Eloquent\Collection $tasks): Collection
    {
        return $tasks->groupBy('location_id')
            ->map(
                /**
                 * @param  \Illuminate\Database\Eloquent\Collection<int, Task>  $grouped
                 * @return Collection<int, array<string, mixed>>
                 */
                static function (\Illuminate\Database\Eloquent\Collection $grouped, int|string|null $key): Collection {
                    return $grouped->map(static function (Task $task): array {
                        /** @var array<string, mixed> $row */
                        $row = [
                            'id' => $task->id,
                            'title' => $task->title,
                            'description' => (string) $task->description,
                            'priority' => [
                                'value' => $task->priority->value,
                                'label' => $task->priority->label(),
                            ],
                            'status' => $task->status,
                            'deadline' => $task->deadline,
                            'estimated_time_minutes' => $task->estimated_time_minutes ?? 0,
                        ];

                        return $row;
                    })->toBase();
                }
            );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Task>  $tasks
     * @return Collection<int|string, array{high: int, normal: int, low: int}>
     */
    public function computeBacklogPriorityCounts(\Illuminate\Database\Eloquent\Collection $tasks): Collection
    {
        return $tasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                return [
                    TaskPriority::HIGH->value => (int) $tasks_for_location->where('priority', TaskPriority::HIGH)->count(),
                    TaskPriority::NORMAL->value => (int) $tasks_for_location->where('priority', TaskPriority::NORMAL)->count(),
                    TaskPriority::LOW->value => (int) $tasks_for_location->where('priority', TaskPriority::LOW)->count(),
                ];
            });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Task>  $tasks
     * @return Collection<int|string, int>
     */
    public function computeBacklogTotalEstimated(\Illuminate\Database\Eloquent\Collection $tasks): Collection
    {
        return $tasks->groupBy('location_id')
            ->map(function ($tasks_for_location) {
                return $tasks_for_location->sum('estimated_time_minutes');
            });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Location>|Collection<int, Location>  $locations
     * @param  Collection<int|string, array{high: int, normal: int, low: int}>  $backlogPriorityCountsByLocation
     * @return Collection<int, Location>
     */
    public function sortLocationsByBacklogCounts(Collection $locations, Collection $backlogPriorityCountsByLocation): Collection
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
     * @return Collection<int, array{planning_id:int,planning_title:string}>
     */
    public function plannedBacklogTasksMap(?Planning $planning = null, bool $excludeCurrent = false): Collection
    {
        $query = PlanningTask::whereNotNull('task_id')->with('planning:id,planned_date');
        if ($planning && $excludeCurrent) {
            $query->where('planning_id', '!=', $planning->id);
        }

        return $query->get()->mapWithKeys(function ($planningTask) {
            return [(int) $planningTask->task_id => [
                'planning_id' => (int) $planningTask->planning->id,
                'planning_title' => (string) $planningTask->planning->planned_date->format('d-m-Y'),
            ]];
        });
    }
}
