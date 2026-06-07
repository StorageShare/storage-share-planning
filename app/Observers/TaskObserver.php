<?php

namespace App\Observers;

use App\Models\Task;

class TaskObserver
{
    /**
     * Handle the Task "creating" event.
     */
    public function creating(Task $task): void
    {
        if (empty($task->priority_updated_at)) {
            $task->priority_updated_at = now();
        }
    }

    /**
     * Handle the Task "updating" event.
     */
    public function updating(Task $task): void
    {
        if ($task->isDirty('priority') && ! $task->isDirty('priority_updated_at')) {
            $task->priority_updated_at = now();
        }
    }
}
