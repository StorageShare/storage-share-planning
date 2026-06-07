<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\PlanningTask;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlanningTaskRejectionService
{
    public function __construct(
        private PlanningTaskSyncService $planningTaskSyncService,
        private PlanningTaskHistoryService $planningTaskHistoryService
    ) {}

    public function reject(Request $request, PlanningTask $planningTask): RedirectResponse|JsonResponse
    {
        $request->validate([
            'review_notes' => ['required', 'string', 'min:3'],
            'create_replacement' => ['nullable'],
        ]);

        $createReplacement = $request->boolean('create_replacement', false);
        $newTaskId = null;

        DB::transaction(function () use ($request, $planningTask, $createReplacement, &$newTaskId) {
            $planningTask->update([
                'status' => TaskStatus::REJECTED,
                'completed_at' => null,
            ]);

            $this->planningTaskSyncService->syncLinkedVehicleTaskStatus($planningTask, TaskStatus::REJECTED);

            if ($createReplacement) {
                $newTaskId = $this->createReplacementTask($request, $planningTask);
            } elseif ($planningTask->task) {
                $planningTask->task->update(['status' => TaskStatus::REJECTED]);
            }

            if ($latestCompletion = $planningTask->completions()->latest()->first()) {
                $latestCompletion->update([
                    'review_notes' => $request->input('review_notes'),
                    'reviewed_at' => now(),
                    'review_outcome' => 'rejected',
                    'reviewed_by' => Auth::id(),
                ]);
            }
        });

        $message = $createReplacement
            ? 'Taak afgekeurd. Een nieuwe taak is aangemaakt in de backlog.'
            : 'Taak afgekeurd. Er is geen nieuwe taak aangemaakt.';

        $planningTask->planning->checkAndUpdateStatus();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => $message,
                'planning_task_id' => $planningTask->id,
                'planning_id' => $planningTask->planning_id,
                'new_status' => TaskStatus::REJECTED->value,
                'replacement_created' => $createReplacement,
                'new_task_id' => $newTaskId,
            ]);
        }

        if ($request->filled('planning_id')) {
            return redirect()->route('plannings.show', $planningTask->planning)
                ->with('success', $message);
        }

        return redirect()->route('plannings.review')->with('success', $message);
    }

    private function createReplacementTask(Request $request, PlanningTask $planningTask): int
    {
        $reason = (string) $request->input('review_notes');
        $prependReason = function (?string $existing) use ($reason) {
            $base = $existing ? ($existing."\n\n") : '';

            return $base.'Reden afwijzing: '.$reason;
        };

        if ($planningTask->task) {
            $originalTask = $planningTask->task;
            $originalTask->update(['status' => TaskStatus::REJECTED]);

            $newTask = $originalTask->replicate();
            $newTask->status = TaskStatus::OPEN;
            $newTask->title = $originalTask->title.' (Herstel)';
            $newTask->created_at = now();
            $newTask->updated_at = now();
            $withReason = $prependReason($newTask->description);
            $newTask->description = $this->planningTaskHistoryService->appendCompletionHistory($planningTask, $withReason);
            $newTask->estimated_time_minutes = $originalTask->estimated_time_minutes;
            $newTask->deadline = $originalTask->deadline;
            $newTask->save();

            $this->copyCompletionPhotosToTask($planningTask, $newTask);
            foreach ($originalTask->taskPhotos as $taskPhoto) {
                $newTask->taskPhotos()->create([
                    'file_path' => $taskPhoto->file_path,
                    'uploaded_at' => now(),
                ]);
            }

            return $newTask->id;
        }

        $descriptionWithReason = $prependReason($planningTask->description);
        $descriptionFull = $this->planningTaskHistoryService->appendCompletionHistory($planningTask, $descriptionWithReason);

        $newBacklogTask = new Task([
            'title' => $planningTask->title.' (Herstel)',
            'description' => $descriptionFull,
            'location_id' => $planningTask->location_id ?? $planningTask->planning->locations()->first()->id,
            'status' => TaskStatus::OPEN,
            'priority' => TaskPriority::NORMAL,
            'estimated_time_minutes' => $planningTask->estimated_time_minutes,
            'created_by' => Auth::id(),
        ]);
        $newBacklogTask->save();

        $this->copyCompletionPhotosToTask($planningTask, $newBacklogTask);

        return $newBacklogTask->id;
    }

    private function copyCompletionPhotosToTask(PlanningTask $planningTask, Task $task): void
    {
        if ($latestCompletion = $planningTask->completions()->latest()->first()) {
            foreach ($latestCompletion->photos as $photo) {
                $task->taskPhotos()->create([
                    'file_path' => $photo->file_path,
                    'uploaded_at' => now(),
                ]);
            }
        }
    }
}
