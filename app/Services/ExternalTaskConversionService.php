<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ExternalTask;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class ExternalTaskConversionService
{
    public function convertToTask(ExternalTask $externalTask, TaskStatus $status = TaskStatus::OPEN): Task
    {
        return DB::transaction(function () use ($externalTask, $status) {
            $task = Task::create([
                'location_id' => $externalTask->location_id,
                'title' => $externalTask->title,
                'description' => $externalTask->description ?? '',
                'feedback_information' => $externalTask->feedback_information,
                'feedback_owner_name' => $externalTask->feedback_owner_name,
                'feedback_emails' => $externalTask->feedback_emails,
                'deadline' => $externalTask->external_deadline_at,
                'estimated_time_minutes' => $externalTask->estimated_time_minutes,
                'priority' => ($externalTask->priority ?? TaskPriority::NORMAL)->value,
                'status' => $status,
            ]);

            $externalTask->comments()->delete();
            $externalTask->delete();

            return $task;
        });
    }
}
