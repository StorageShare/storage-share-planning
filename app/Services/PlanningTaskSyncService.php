<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Events\LocationCompleted;
use App\Models\Location;
use App\Models\PlanningTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlanningTaskSyncService
{
    public function syncLinkedVehicleTaskStatus(PlanningTask $planningTask, TaskStatus $status): void
    {
        try {
            if ($planningTask->is_vehicle_task && $planningTask->vehicleTask) {
                $planningTask->vehicleTask->update(['status' => $status]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed syncing vehicle task status: '.$e->getMessage());
        }
    }

    public function syncLinkedBacklogTaskStatus(PlanningTask $planningTask, TaskStatus $status): void
    {
        if ($planningTask->task) {
            $planningTask->task->update(['status' => $status]);
        }
    }

    public function syncLinkedTasks(PlanningTask $planningTask, TaskStatus $status): void
    {
        $this->syncLinkedVehicleTaskStatus($planningTask, $status);
        $this->syncLinkedBacklogTaskStatus($planningTask, $status);
    }

    public function checkLocationCompletionAndNotify(PlanningTask $planningTask): void
    {
        $planning = $planningTask->planning;

        $location = null;
        if ($planningTask->location_id) {
            $location = Location::find($planningTask->location_id);
        } elseif ($planningTask->task && $planningTask->task->location_id) {
            $location = $planningTask->task->location;
        }

        if (! $location) {
            return;
        }

        if ($location->areAllTasksCompletedInPlanning($planning)) {
            $cacheKey = "location_completed_notified_{$planning->id}_{$location->id}";

            if (! Cache::has($cacheKey)) {
                LocationCompleted::dispatch($location, $planning);
                Cache::put($cacheKey, true, now()->addDay());
            }
        }
    }
}
