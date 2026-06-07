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

class PlanningTaskCreationService
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
    public function create(Planning $planning, array $validatedData): void
    {
        if ($planning->vehicle_id) {
            $openVehicleTasks = VehicleTask::where('vehicle_id', $planning->vehicle_id)
                ->where('status', TaskStatus::OPEN->value)
                ->orderBy('created_at')
                ->get();

            foreach ($openVehicleTasks as $vt) {
                $planning->planningTasks()->create([
                    'vehicle_task_id' => $vt->id,
                    'title' => $vt->title,
                    'description' => $vt->description ?? '',
                    'status' => TaskStatus::OPEN,
                    'estimated_time_minutes' => $vt->estimated_time_minutes,
                    'is_vehicle_task' => true,
                ]);
            }
        }

        if (! empty($validatedData['selected_default_tasks']) && ! empty($validatedData['location_ids'])) {
            /** @var array<int,int> $locIds */
            $locIds = array_map('intval', $validatedData['location_ids']);
            $selected_location_ids = collect($locIds);
            /** @var array<int,int> $defaultIds */
            $defaultIds = array_map('intval', $validatedData['selected_default_tasks']);
            $default_task_templates = DefaultTask::with('locations')->findMany($defaultIds);
            $locations = Location::findMany($selected_location_ids);

            foreach ($selected_location_ids as $location_id) {
                $location = $locations->firstWhere('id', $location_id);
                if (! $location) {
                    continue;
                }

                foreach ($default_task_templates as $template) {
                    if ($template->locations->contains('id', $location_id)) {
                        $estimatedTime = $template->calculateEstimatedTime($location);

                        $newTask = Task::create([
                            'location_id' => $location_id,
                            'title' => $template->title,
                            'description' => $template->description ?? '',
                            'feedback_information' => $template->feedback_information,
                            'feedback_owner_name' => $template->feedback_owner_name,
                            'feedback_emails' => $template->feedback_emails,
                            'estimated_time_minutes' => $estimatedTime,
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
                            'location_id' => $location_id,
                            'task_id' => $newTask->id,
                            'title' => $template->title,
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

        if (! empty($validatedData['selected_backlog_tasks'])) {
            /** @var array<int,int> $backlogIds */
            $backlogIds = array_map('intval', $validatedData['selected_backlog_tasks']);
            $backlogTasks = Task::query()
                ->whereIn('id', $backlogIds)
                ->get();
            foreach ($backlogTasks as $backlogTask) {
                $planning->planningTasks()->create([
                    'task_id' => $backlogTask->id,
                    'title' => $backlogTask->title,
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

        foreach ($planning->locations as $location) {
            Log::debug('Checking inactive spaces for location', [
                'location_id' => $location->id,
                'sync_external_id' => $location->sync_external_id,
                'check_inactive_spaces' => $location->pivot->check_inactive_spaces,
            ]);

            $effectiveSyncId = $location->sync_external_id ?: $location->external_id;

            if ($location->pivot->check_inactive_spaces && $effectiveSyncId) {
                $inactiveRooms = $this->externalLocationService->fetchInactiveRooms($effectiveSyncId);
                Log::debug('Inactive rooms fetched', [
                    'location_id' => $location->id,
                    'sync_id' => $effectiveSyncId,
                    'count' => is_array($inactiveRooms) ? count($inactiveRooms) : 'null',
                ]);

                if ($inactiveRooms) {
                    foreach ($inactiveRooms as $roomData) {
                        $room = $roomData['name'];
                        $description = $roomData['description'] ?? 'Controleer de inactieve ruimte op bijzonderheden.';
                        $group = $roomData['group_name'] ?? null;

                        $exists = $planning->planningTasks()
                            ->where('location_id', $location->id)
                            ->where('room_identifier', $room)
                            ->exists();

                        if (! $exists) {
                            $planning->planningTasks()->create([
                                'location_id' => $location->id,
                                'title' => 'Inactieve ruimte controleren: '.$room,
                                'description' => $description,
                                'status' => TaskStatus::OPEN,
                                'priority' => TaskPriority::NORMAL,
                                'estimated_time_minutes' => 5,
                                'room_identifier' => $room,
                                'room_group' => $group,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
