<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Mail\TaskCompletedApprovedMail;
use App\Models\PlanningTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class PlanningTaskApprovalService
{
    public function __construct(
        private PlanningTaskSyncService $planningTaskSyncService
    ) {}

    public function approve(Request $request, PlanningTask $planningTask): RedirectResponse|JsonResponse
    {
        $planningTask->update(['status' => TaskStatus::COMPLETED]);

        $this->planningTaskSyncService->syncLinkedTasks($planningTask, TaskStatus::COMPLETED);

        if ($latestCompletion = $planningTask->completions()->latest()->first()) {
            $latestCompletion->update([
                'review_notes' => $request->input('review_notes'),
                'reviewed_at' => now(),
                'review_outcome' => 'approved',
                'reviewed_by' => Auth::id(),
            ]);

            if ($planningTask->feedback_emails) {
                $emails = preg_split('/[;,]+/', (string) $planningTask->feedback_emails) ?: [];
                $emails = array_map(fn ($e) => strtolower(trim($e)), $emails);
                $emails = array_values(array_unique(array_filter($emails)));
                $validEmails = array_values(array_filter($emails, fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL)));

                if (! empty($validEmails)) {
                    Mail::to($validEmails)->send(new TaskCompletedApprovedMail($planningTask, $latestCompletion));
                }
            }
        }

        $planningTask->planning->checkAndUpdateStatus();

        $message = 'Geplande taak goedgekeurd.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => $message,
                'planning_task_id' => $planningTask->id,
                'planning_id' => $planningTask->planning_id,
                'new_status' => TaskStatus::COMPLETED->value,
            ]);
        }

        if ($request->filled('planning_id')) {
            return redirect()->route('plannings.show', $planningTask->planning)->with('success', $message);
        }

        return redirect()->route('plannings.review')->with('success', $message);
    }
}
