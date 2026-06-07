<?php

namespace App\Http\Controllers;

use App\Models\Planning;
use App\Models\PlanningComment;
use App\Models\PlanningTask;
use App\Services\ImageService;
use App\Services\PlanningCommentService;
use App\Services\PlanningTaskApprovalService;
use App\Services\PlanningTaskCompletionWorkflowService;
use App\Services\PlanningTaskPhotoDownloadService;
use App\Services\PlanningTaskRejectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlanningTaskController extends Controller
{
    public function __construct(
        private PlanningTaskCompletionWorkflowService $planningTaskCompletionWorkflowService,
        private PlanningTaskApprovalService $planningTaskApprovalService,
        private PlanningTaskRejectionService $planningTaskRejectionService,
        private PlanningCommentService $planningCommentService,
        private PlanningTaskPhotoDownloadService $planningTaskPhotoDownloadService
    ) {}

    public function show(PlanningTask $planning_task): View
    {
        $planning_task->load([
            'planning.locations',
            'defaultTask',
            'specificLocation',
            'completions' => function ($query) {
                $query->with(['user', 'photos', 'reviewer'])->orderBy('created_at', 'desc');
            },
        ]);

        return view($this->viewName('plannings.tasks.show'), compact('planning_task'));
    }

    public function complete(Request $request, Planning $planning, PlanningTask $planning_task, ImageService $imageService): RedirectResponse
    {
        return $this->planningTaskCompletionWorkflowService->complete($request, $planning, $planning_task);
    }

    public function uncomplete(Request $request, Planning $planning, PlanningTask $planning_task): RedirectResponse
    {
        return $this->planningTaskCompletionWorkflowService->uncomplete($request, $planning, $planning_task);
    }

    public function approve(Request $request, PlanningTask $planning_task): RedirectResponse|JsonResponse
    {
        return $this->planningTaskApprovalService->approve($request, $planning_task);
    }

    public function reject(Request $request, PlanningTask $planning_task): RedirectResponse|JsonResponse
    {
        return $this->planningTaskRejectionService->reject($request, $planning_task);
    }

    public function simpleComplete(Request $request, Planning $planning, PlanningTask $planning_task): JsonResponse
    {
        return $this->planningTaskCompletionWorkflowService->simpleComplete($planning, $planning_task);
    }

    public function simpleUncomplete(Request $request, Planning $planning, PlanningTask $planning_task): JsonResponse
    {
        return $this->planningTaskCompletionWorkflowService->simpleUncomplete($planning, $planning_task);
    }

    public function submitCompletion(Request $request, Planning $planning, PlanningTask $planning_task, ImageService $imageService): JsonResponse
    {
        return $this->planningTaskCompletionWorkflowService->submitCompletion($request, $planning, $planning_task);
    }

    public function skip(Request $request, Planning $planning, PlanningTask $planning_task, ImageService $imageService): JsonResponse
    {
        return $this->planningTaskCompletionWorkflowService->skip($request, $planning, $planning_task);
    }

    public function reopen(Request $request, Planning $planning, PlanningTask $planning_task): JsonResponse
    {
        return $this->planningTaskCompletionWorkflowService->reopen($planning, $planning_task);
    }

    public function storeExtraTask(Request $request, Planning $planning, int|string $location_id, ImageService $imageService): JsonResponse
    {
        return $this->planningCommentService->storeExtraTask($request, $planning, $location_id);
    }

    public function updateComment(Request $request, PlanningComment $comment, ImageService $imageService): JsonResponse
    {
        return $this->planningCommentService->updateComment($request, $comment);
    }

    public function downloadPhotos(PlanningTask $planning_task): BinaryFileResponse|RedirectResponse
    {
        return $this->planningTaskPhotoDownloadService->download($planning_task);
    }
}
