<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Http\Controllers\PlanningTaskController;
use App\Models\DefaultTask;
use App\Models\Planning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanningCompletionService
{
    public function complete(Planning $planning): void
    {
        DB::transaction(function () use ($planning) {
            $submittedTasks = $planning->planningTasks()
                ->whereIn('status', [
                    TaskStatus::REVIEW->value,
                    TaskStatus::IN_REVIEW->value,
                ])->get();

            if ($submittedTasks->isNotEmpty()) {
                $ptController = new PlanningTaskController;
                foreach ($submittedTasks as $pt) {
                    try {
                        $req = new Request;
                        $req->merge(['planning_id' => $planning->id]);
                        $ptController->approve($req, $pt);
                    } catch (\Throwable $e) {
                        Log::warning('Automatische goedkeuring bij afronden planning faalde', [
                            'planning_id' => $planning->id,
                            'planning_task_id' => $pt->id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $planning->cleanupUncompletedDefaultTasks();

            $uncompletedBacklogPlanningTasks = $planning->planningTasks()
                ->whereNotNull('task_id')
                ->where('status', '!=', TaskStatus::COMPLETED->value)
                ->get();

            foreach ($uncompletedBacklogPlanningTasks as $pt) {
                if ($pt->task) {
                    $isDefaultTask = DefaultTask::where(function ($query) use ($pt) {
                        $query->where('applies_to_all_locations', true)
                            ->orWhereHas('locations', function ($q) use ($pt) {
                                $q->where('locations.id', $pt->location_id);
                            });
                    })->where('title', $pt->title)->exists();

                    if ($isDefaultTask) {
                        $pt->task->delete();
                    } else {
                        $pt->task->update(['status' => TaskStatus::OPEN]);
                    }
                }
                $pt->delete();
            }

            $planning->planningTasks()
                ->whereNotNull('room_identifier')
                ->where('status', '!=', TaskStatus::COMPLETED->value)
                ->delete();

            $planning->update([
                'status' => 'completed',
            ]);
        });
    }
}
