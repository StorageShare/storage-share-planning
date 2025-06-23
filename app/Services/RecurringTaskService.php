<?php

namespace App\Services;

use App\Models\Task;
use App\Enums\TaskStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RecurringTaskService
{
    /**
     * Create a new recurring instance directly when a task is approved.
     */
    public function createRecurringInstance(Task $task): ?Task
    {
        // Only process original recurring tasks (not generated instances)
        if (!$this->shouldCreateRecurringInstance($task)) {
            return null;
        }

        try {
            // Calculate next deadline from current date
            $nextDeadline = $task->calculateNextRecurringDate(now()->toDateTime());
            
            if (!$nextDeadline) {
                Log::warning("Could not calculate next recurring date for task {$task->id}");
                return null;
            }

            // Create new recurring instance
            $newTask = $task->createRecurringInstance(Carbon::instance($nextDeadline));

            Log::info("Created recurring task instance", [
                'original_task_id' => $task->id,
                'new_task_id' => $newTask->id,
                'next_deadline' => $nextDeadline->format('Y-m-d'),
            ]);

            return $newTask;

        } catch (\Exception $e) {
            Log::error("Failed to create recurring task instance", [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Determine if a task should generate a new recurring instance.
     */
    private function shouldCreateRecurringInstance(Task $task): bool
    {
        return $task->is_recurring 
            && is_null($task->parent_recurring_task_id) // Only original tasks, not instances
            && $task->recurring_interval_type
            && $task->recurring_interval_value > 0;
    }
} 