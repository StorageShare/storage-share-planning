<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Events\TaskReadyForReview;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlanningTaskCompletionWorkflowService
{
    public function __construct(
        private PlanningTaskSyncService $planningTaskSyncService,
        private PlanningTaskCompletionPhotoService $planningTaskCompletionPhotoService
    ) {}

    public function complete(Request $request, Planning $planning, PlanningTask $planningTask): RedirectResponse
    {
        $this->assertBelongsToPlanning($planning, $planningTask);

        /** @var User $user */
        $user = Auth::user();

        $validationRules = [
            'completed_notes' => 'required|string|max:65535',
            'is_fully_completed' => 'required|boolean',
        ];

        if ($user == null || ! $user->isAdmin()) {
            $validationRules['photos'] = 'required|array|min:1';
            $validationRules['photos.*'] = 'image|mimes:jpeg,png,jpg,webp,gif|max:20480';
        } else {
            $validationRules['photos'] = 'nullable|array';
            $validationRules['photos.*'] = 'image|mimes:jpeg,png,jpg,webp,gif|max:20480';
        }

        $request->validate($validationRules);

        $isFullyCompleted = $request->boolean('is_fully_completed');

        $completion = $planningTask->completions()->create([
            'user_id' => $user->id,
            'comment' => $request->input('completed_notes'),
            'is_fully_completed' => $isFullyCompleted,
        ]);

        $this->planningTaskCompletionPhotoService->copyFromPreviousCompletion($planningTask, $completion);
        $this->planningTaskCompletionPhotoService->storeUploadedPhotos($request, $completion);

        $newStatus = $user != null && $user->isAdmin() ? TaskStatus::COMPLETED : TaskStatus::REVIEW;
        $this->markCompleted($planningTask, $request->input('completed_notes'), $newStatus);

        if ($planningTask->task && $newStatus === TaskStatus::REVIEW) {
            event(new TaskReadyForReview($planningTask->task));
        }

        $message = $isFullyCompleted
            ? "Taak '{$planningTask->title}' gemarkeerd voor review."
            : "Voltooiingspoging voor '{$planningTask->title}' genoteerd en voor review aangeboden.";
        if ($user != null && $user->isAdmin()) {
            $message = "Taak '{$planningTask->title}' als voltooid gemarkeerd.";
        }

        $planning->checkAndUpdateStatus();

        return redirect()->route('plannings.show', $planning)->with('success', $message);
    }

    public function uncomplete(Request $request, Planning $planning, PlanningTask $planningTask): RedirectResponse
    {
        $this->assertBelongsToPlanning($planning, $planningTask);

        /** @var User $user */
        $user = Auth::user();
        if ($user->isAdmin()) {
            $request->validate([
                'rejection_reason' => 'required|string|max:65535',
            ]);

            $planningTask->completions()->create([
                'user_id' => $user->id,
                'comment' => 'Taak heropend door admin.',
                'is_fully_completed' => false,
                'review_notes' => $request->input('rejection_reason'),
                'reviewed_at' => now(),
                'review_outcome' => 'reopened',
                'reviewed_by' => $user->id,
            ]);
        }

        $this->markOpen($planningTask);
        $planning->checkAndUpdateStatus();

        return redirect()->route('plannings.show', $planning)->with('success', "Taak '{$planningTask->title}' als openstaand gemarkeerd.");
    }

    public function simpleComplete(Planning $planning, PlanningTask $planningTask): JsonResponse
    {
        $this->assertBelongsToPlanning($planning, $planningTask);

        $this->markCompleted($planningTask, null, TaskStatus::COMPLETED);
        $planning->checkAndUpdateStatus();

        return response()->json(['task' => $planningTask->fresh(['completions.photos'])]);
    }

    public function simpleUncomplete(Planning $planning, PlanningTask $planningTask): JsonResponse
    {
        $this->assertBelongsToPlanning($planning, $planningTask);

        $this->markOpen($planningTask);
        $planning->checkAndUpdateStatus();

        return response()->json(['task' => $planningTask->fresh(['completions.photos'])]);
    }

    public function submitCompletion(Request $request, Planning $planning, PlanningTask $planningTask): JsonResponse
    {
        $this->assertBelongsToPlanning($planning, $planningTask);

        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'completed_notes' => 'required|string|max:65535',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp,gif|max:20480',
        ]);

        $isPhotoRequired = (bool) ($planningTask->task->is_photo_required ?? $planningTask->defaultTask->is_photo_required ?? false);
        if ($isPhotoRequired && ! $request->hasFile('photos')) {
            $existingPhotosCount = $planningTask->completions()
                ->where('review_outcome', '!=', 'reopened')
                ->latest()
                ->first()?->photos()->count() ?? 0;

            if ($existingPhotosCount === 0) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['photos' => ['Foto is verplicht voor deze taak.']],
                ], 422);
            }
        }

        $completion = $planningTask->completions()->create([
            'user_id' => $user->id,
            'comment' => $request->input('completed_notes'),
            'is_fully_completed' => true,
            'task_duration_seconds' => $request->input('task_duration_seconds', 0),
        ]);

        $this->planningTaskCompletionPhotoService->copyFromPreviousCompletion($planningTask, $completion);
        $this->planningTaskCompletionPhotoService->storeUploadedPhotos($request, $completion);

        $this->markCompleted($planningTask, $request->input('completed_notes'), TaskStatus::REVIEW);
        $planning->checkAndUpdateStatus();

        return response()->json(['task' => $planningTask->fresh(['completions.photos'])]);
    }

    public function skip(Request $request, Planning $planning, PlanningTask $planningTask): JsonResponse
    {
        $this->assertBelongsToPlanning($planning, $planningTask);

        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'reason' => 'required|string|max:65535',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp,gif|max:20480',
        ]);

        $completion = $planningTask->completions()->create([
            'user_id' => $user->id,
            'comment' => $request->input('reason'),
            'is_fully_completed' => false,
            'review_outcome' => 'skipped',
            'task_duration_seconds' => $request->input('task_duration_seconds', 0),
        ]);

        $this->planningTaskCompletionPhotoService->storeUploadedPhotos($request, $completion);

        $planningTask->update(['status' => TaskStatus::SKIPPED]);
        $this->planningTaskSyncService->syncLinkedTasks($planningTask, TaskStatus::SKIPPED);

        $completion->load('photos');
        $skipPhotos = $completion->photos->pluck('url')->toArray();

        return response()->json([
            'task' => $planningTask->fresh(['completions.photos']),
            'skip_photos' => $skipPhotos,
        ]);
    }

    public function reopen(Planning $planning, PlanningTask $planningTask): JsonResponse
    {
        $this->assertBelongsToPlanning($planning, $planningTask);

        if (! in_array($planningTask->status, [TaskStatus::REVIEW, TaskStatus::SKIPPED, TaskStatus::REJECTED], true)) {
            return response()->json(['message' => 'Taak kan niet heropend worden.'], 403);
        }

        $this->markOpen($planningTask);
        $planning->checkAndUpdateStatus();

        return response()->json([
            'task' => $planningTask->fresh(['completions.photos']),
        ]);
    }

    private function markCompleted(PlanningTask $planningTask, ?string $notes, TaskStatus $status): void
    {
        $planningTask->update([
            'completed_at' => now(),
            'completed_notes' => $notes,
            'status' => $status,
        ]);

        $this->planningTaskSyncService->syncLinkedTasks($planningTask, $status);
    }

    private function markOpen(PlanningTask $planningTask): void
    {
        $planningTask->update([
            'completed_at' => null,
            'completed_notes' => null,
            'status' => TaskStatus::OPEN,
        ]);

        $this->planningTaskSyncService->syncLinkedTasks($planningTask, TaskStatus::OPEN);
    }

    private function assertBelongsToPlanning(Planning $planning, PlanningTask $planningTask): void
    {
        if ($planningTask->planning_id !== $planning->id) {
            abort(404);
        }
    }
}
