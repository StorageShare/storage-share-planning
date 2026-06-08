<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\PlanningTask;
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
}
