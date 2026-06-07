<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Planning;
use App\Models\Task;
use App\Models\VehicleTask;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PlanningTaskUpdateService
{
    public function __construct(
        private ExternalLocationService $externalLocationService
    ) {}

    /**
     * @param array{
     *   selected_default_tasks?: array<int,int>,
     *   selected_backlog_tasks?: array<int,int>,
     *   location_ids?: array<int,int>
     * } $validatedData
     */
    public function update(Planning $planning, array $validatedData): void
    {
        if ($planning->vehicle_id) {
            $existingLinkedVehicleTaskIds = $planning->planningTasks()
                ->where('is_vehicle_task', true)
                ->whereNotNull('vehicle_task_id')
                ->pluck('vehicle_task_id');

            $openVehicleTasks = VehicleTask::where('vehicle_id', $planning->vehicle_id)
                ->where('status', TaskStatus::OPEN->value)
                ->whereNotIn('id', $existingLinkedVehicleTaskIds)
                ->orderBy('created_at')
                ->get();

            foreach ($openVehicleTasks as $vt) {
                $planning->planningTasks()->create([
                    'vehicle_task_id' => $vt->id,
                    'title' => $vt->title,
                    'description' => $vt->description,
                    'status' => TaskStatus::OPEN,
                    'estimated_time_minutes' => $vt->estimated_time_minutes,
                    'is_vehicle_task' => true,
                ]);
            }
        }

        $current_default_planning_tasks = $planning->planningTasks()
            ->whereNotNull('default_task_id')
            ->get()
            ->keyBy(fn ($pt) => $pt->location_id.'-'.$pt->default_task_id);

        $desired_default_task_state = collect();
        if (! empty($validatedData['selected_default_tasks']) && ! empty($validatedData['location_ids'])) {
            /** @var array<int,int> $locIds2 */
            $locIds2 = array_map('intval', $validatedData['location_ids']);
            $selected_location_ids_for_planning = collect($locIds2);
            /** @var array<int,int> $defaultIds2 */
            $defaultIds2 = array_map('intval', $validatedData['selected_default_tasks']);
            $default_task_templates = DefaultTask::with('locations')->findMany($defaultIds2);
            $locations = Location::findMany($selected_location_ids_for_planning);

            foreach ($selected_location_ids_for_planning as $location_id_for_planning) {
                $location = $locations->firstWhere('id', $location_id_for_planning);
                if (! $location) {
                    continue;
                }

                foreach ($default_task_templates as $default_task_template) {
                    if ($default_task_template->locations->contains('id', $location_id_for_planning)) {
                        $estimatedTime = $default_task_template->calculateEstimatedTime($location);
                        $desired_default_task_state->put($location_id_for_planning.'-'.$default_task_template->id, [
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

        $default_duplicated_task_ids = collect();

        $tasks_to_add_data = $desired_default_task_state->diffKeys($current_default_planning_tasks);
        foreach ($tasks_to_add_data as $data) {
            $template = DefaultTask::find($data['default_task_id']);
            if ($template) {
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

                $default_duplicated_task_ids->push($newTask->id);
            }
        }

        $selected_backlog_task_ids_input = $validatedData['selected_backlog_tasks'] ?? [];
        /** @var array<int,int|string> $selected_backlog_task_ids_input */
        $selected_backlog_task_ids = collect($selected_backlog_task_ids_input)->map(fn ($id) => (int) $id);
        $current_planning_tasks_from_backlog = $planning->planningTasks()
            ->whereNotNull('task_id')
            ->whereNull('default_task_id')
            ->whereNull('vehicle_task_id')
            ->get();

        $backlog_task_ids_to_delete_from_planning = $current_planning_tasks_from_backlog
            ->filter(fn ($pt) => ! $selected_backlog_task_ids->contains($pt->task_id)
                && ! $default_duplicated_task_ids->contains($pt->task_id))
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

        $current_inactive_planning_tasks = $planning->planningTasks()
            ->whereNotNull('room_identifier')
            ->get()
            ->keyBy(fn ($pt) => $pt->location_id.'-'.$pt->room_identifier);

        $desired_inactive_task_state = collect();
        foreach ($planning->locations as $location) {
            Log::debug('Updating inactive spaces for location', [
                'location_id' => $location->id,
                'sync_external_id' => $location->sync_external_id,
                'check_inactive_spaces' => $location->pivot->check_inactive_spaces,
            ]);

            $effectiveSyncId = $location->sync_external_id ?: $location->external_id;

            if ($location->pivot->check_inactive_spaces && $effectiveSyncId) {
                $inactiveRooms = $this->externalLocationService->fetchInactiveRooms($effectiveSyncId);
                Log::debug('Inactive rooms fetched for update', [
                    'location_id' => $location->id,
                    'sync_id' => $effectiveSyncId,
                    'count' => is_array($inactiveRooms) ? count($inactiveRooms) : 'null',
                ]);

                if ($inactiveRooms) {
                    foreach ($inactiveRooms as $roomData) {
                        $room = $roomData['name'];
                        $description = $roomData['description'] ?? 'Controleer de inactieve ruimte op bijzonderheden.';
                        $group = $roomData['group_name'] ?? null;

                        $desired_inactive_task_state->put($location->id.'-'.$room, [
                            'location_id' => $location->id,
                            'room_identifier' => $room,
                            'room_group' => $group,
                            'title' => 'Inactieve ruimte controleren: '.$room,
                            'description' => $description,
                            'status' => TaskStatus::OPEN,
                            'priority' => TaskPriority::NORMAL,
                            'estimated_time_minutes' => 5,
                        ]);
                    }
                }
            }
        }

        $inactive_task_ids_to_delete = $current_inactive_planning_tasks->diffKeys($desired_inactive_task_state)->pluck('id');
        if ($inactive_task_ids_to_delete->isNotEmpty()) {
            $planning->planningTasks()->whereIn('id', $inactive_task_ids_to_delete)->delete();
        }

        $inactive_tasks_to_add_data = $desired_inactive_task_state->diffKeys($current_inactive_planning_tasks);
        foreach ($inactive_tasks_to_add_data as $data) {
            $planning->planningTasks()->create($data);
        }
    }
}
