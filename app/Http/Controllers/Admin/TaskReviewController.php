<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\EndChecklistItem;
use App\Models\PlanningTask;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TaskReviewController extends Controller
{
    /**
     * Display a listing of the tasks to be reviewed.
     */
    public function index(): View
    {
        // Get backlog tasks that are in review
        $review_tasks = Task::where('status', TaskStatus::REVIEW->value)
            ->with(['location', 'planningTasks.completions.user'])
            ->get();

        // Get planned tasks (not from backlog) that are in review
        $review_planning_tasks = PlanningTask::where('status', TaskStatus::REVIEW->value)
            ->whereNull('task_id') // only those not originating from a backlog task
            ->with(['planning.locations', 'specificLocation', 'completions.user'])
            ->get();

        // Get skipped tasks that need admin review
        $skipped_planning_tasks = PlanningTask::where('status', TaskStatus::SKIPPED->value)
            ->with(['planning.locations', 'specificLocation', 'completions.user', 'task.location', 'defaultTask'])
            ->get();

        // Get pending end checklist items (grouped only for same requirement with same title)
        $pending_checklist_items = EndChecklistItem::where('status', 'pending')
            ->with(['planning.users', 'planning.locations', 'location', 'uploader', 'requirement'])
            ->get()
            ->groupBy(function ($item) {
                // Group by type, requirement_id (for materials) or title (for end_actions), AND title
                if ($item->type === 'material' && $item->requirement_id) {
                    return 'material_'.$item->requirement_id.'_'.$item->title;
                } else {
                    return 'end_action_'.$item->title;
                }
            })
            ->map(function ($group) {
                // For each group, return the first item
                $firstItem = $group->first();
                $firstItem->item_count = $group->count();
                $firstItem->all_items = $group;

                return $firstItem;
            })
            ->values();

        $combined_list = new Collection;

        foreach ($review_tasks as $task) {
            // Find the planning task that put this task into review
            $triggering_planning_task = $task->planningTasks
                ->where('status', TaskStatus::REVIEW->value)
                ->sortByDesc('completed_at')
                ->first();

            // Find the user from the last completion of that planning task
            $completed_by_user = $triggering_planning_task?->completions->last()?->user;

            $combined_list->push((object) [
                'title' => $task->title,
                'type' => 'task',
                'item' => $task,
                'location' => $task->location->name,
                'completed_at' => $triggering_planning_task?->completed_at,
                'completed_by' => $completed_by_user?->name,
                'status_type' => 'review',
            ]);
        }

        foreach ($review_planning_tasks as $planning_task) {
            $location_name = $planning_task->specificLocation->name ?? $planning_task->planning->locations->pluck('name')->implode(', ');
            $completed_by_user = $planning_task->completions->last()?->user;

            $combined_list->push((object) [
                'title' => $planning_task->title,
                'type' => 'planning_task',
                'item' => $planning_task,
                'location' => $location_name,
                'completed_at' => $planning_task->completed_at,
                'completed_by' => $completed_by_user?->name,
                'status_type' => 'review',
            ]);
        }

        foreach ($skipped_planning_tasks as $planning_task) {
            $location_name = $planning_task->specificLocation->name ??
                           $planning_task->task?->location->name ??
                           $planning_task->planning->locations->pluck('name')->implode(', ');
            $skipped_by_user = $planning_task->completions->where('review_outcome', 'skipped')->last()?->user;

            $combined_list->push((object) [
                'title' => $planning_task->title,
                'type' => 'skipped_planning_task',
                'item' => $planning_task,
                'location' => $location_name,
                'completed_at' => $planning_task->completions->where('review_outcome', 'skipped')->last()?->created_at,
                'completed_by' => $skipped_by_user?->name,
                'status_type' => 'skipped',
            ]);
        }

        foreach ($pending_checklist_items as $checklist_item) {
            // For checklist items, show who uploaded the photo (completed the item)
            $completed_by = $checklist_item->uploader ?
                $checklist_item->uploader->name :
                'Onbekend';

            $combined_list->push((object) [
                'title' => $checklist_item->title,
                'type' => 'end_checklist_item',
                'item' => $checklist_item,
                'location' => '', // Don't show location in the list
                'completed_at' => $checklist_item->uploaded_at ?? $checklist_item->created_at,
                'completed_by' => $completed_by,
                'status_type' => 'end_checklist',
            ]);
        }

        // Sort the combined list by completion date, newest first
        $tasks_to_review = $combined_list->sortByDesc('completed_at');

        return view($this->viewName('admin.tasks.review'), compact('tasks_to_review'));
    }

    /**
     * Display the specified resource for review.
     */
    public function show(string $type, int $id): View
    {
        $task_item = null;
        $triggering_planning_task = null;
        $planning = null;

        if ($type === 'task') {
            $task = Task::with([
                'location',
                'planningTasks' => function ($query) {
                    $query->where('status', TaskStatus::REVIEW->value)
                        ->with(['completions' => function ($completionQuery) {
                            $completionQuery->with(['user', 'photos'])->orderBy('created_at', 'asc');
                        }, 'planning'])
                        ->latest('completed_at');
                },
            ])->findOrFail($id);

            $triggering_planning_task = $task->planningTasks->first();

            if ($triggering_planning_task) {
                $planning = $triggering_planning_task->planning;
            }

            $completion_history = $triggering_planning_task->completions ?? collect();

            $task_item = (object) [
                'item' => $task,
                'title' => $task->title,
                'type' => 'task',
                'description' => $triggering_planning_task->description ?? $task->description,
                'location' => $task->location->name,
                'planning' => $planning,
                'history' => $completion_history,
                'approve_route' => route('tasks.approve', $task),
                'reject_route' => route('tasks.reject', $task),
            ];

        } elseif ($type === 'planning_task') {
            $task_item = PlanningTask::with([
                'specificLocation',
                'task',
                'planning',
                'completions' => function ($query) {
                    $query->with(['user', 'photos'])->orderBy('created_at', 'asc');
                },
            ])->findOrFail($id);

            $location_name = $task_item->specificLocation->name ?? $task_item->planning->locations->pluck('name')->implode(', ');
            $completion_history = $task_item->completions;
            $planning = $task_item->planning;

            $task_item = (object) [
                'item' => $task_item,
                'title' => $task_item->title,
                'type' => 'planning_task',
                'description' => $task_item->description,
                'location' => $location_name,
                'planning' => $planning,
                'history' => $completion_history,
                'approve_route' => route('plannings.tasks.approve', $task_item),
                'reject_route' => route('plannings.tasks.reject', $task_item),
            ];

        } elseif ($type === 'skipped_planning_task') {
            $task_item = PlanningTask::with([
                'specificLocation',
                'task.location',
                'defaultTask',
                'planning',
                'completions' => function ($query) {
                    $query->with(['user', 'photos'])->orderBy('created_at', 'asc');
                },
            ])->findOrFail($id);

            $location_name = $task_item->specificLocation->name ??
                           $task_item->task->location->name ??
                           $task_item->planning->locations->pluck('name')->implode(', ');
            $completion_history = $task_item->completions;
            $planning = $task_item->planning;

            $task_item = (object) [
                'item' => $task_item,
                'title' => $task_item->title,
                'type' => 'skipped_planning_task',
                'description' => $task_item->description,
                'location' => $location_name,
                'planning' => $planning,
                'history' => $completion_history,
                'approve_route' => null, // Will be handled differently for skipped tasks
                'reject_route' => null, // Will be handled differently for skipped tasks
            ];

        } elseif ($type === 'end_checklist_item') {
            $checklist_item = EndChecklistItem::with([
                'planning.users',
                'planning.locations',
                'location',
                'uploader',
                'requirement',
            ])->findOrFail($id);

            // Get all related items (same requirements AND title, or same end_action title)
            $related_items = collect([$checklist_item]);
            if ($checklist_item->type === 'material' && $checklist_item->requirement_id) {
                $related_items = EndChecklistItem::where('type', 'material')
                    ->where('requirement_id', $checklist_item->requirement_id)
                    ->where('title', $checklist_item->title)
                    ->where('status', 'pending')
                    ->with(['location', 'uploader', 'planning'])
                    ->get();
            } else {
                $related_items = EndChecklistItem::where('type', 'end_action')
                    ->where('title', $checklist_item->title)
                    ->where('status', 'pending')
                    ->with(['location', 'uploader', 'planning'])
                    ->get();
            }

            // Use specific location if available, otherwise planning locations
            $location_name = $checklist_item->location ?
                $checklist_item->location->name :
                $checklist_item->planning->locations->pluck('name')->implode(', ');

            $planning = $checklist_item->planning;
            $completion_history = collect(); // No completion history for checklist items

            $task_item = (object) [
                'item' => $checklist_item,
                'title' => $checklist_item->title,
                'type' => 'end_checklist_item',
                'description' => $checklist_item->description,
                'location' => $location_name,
                'planning' => $planning,
                'history' => $completion_history,
                'approve_route' => route('admin.end-checklist.approve', $checklist_item),
                'reject_route' => route('admin.end-checklist.reject', $checklist_item),
                'photo_url' => $checklist_item->photo_path ? Storage::disk('public')->url($checklist_item->photo_path) : null,
                'checklist_type' => $checklist_item->type,
                'admin_notes' => $checklist_item->admin_notes,
                'uploader_name' => $checklist_item->uploader?->name,
                'uploaded_at' => $checklist_item->uploaded_at,
                'specific_location' => $checklist_item->location,
                'related_items' => $related_items,
            ];
        } else {
            abort(404);
        }

        return view($this->viewName('admin.tasks.show'), [
            'task' => $task_item,
            'type' => $type,
            'completion_history' => $completion_history,
            'planning' => $planning,
        ]);
    }

    /**
     * Review a skipped planning task and decide whether to add it back to backlog.
     */
    public function reviewSkipped(Request $request, PlanningTask $planning_task): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:add_to_backlog,dismiss',
            'review_notes' => 'nullable|string|max:65535',
        ]);

        $action = $request->input('action');
        $reviewNotes = $request->input('review_notes');

        if ($action === 'add_to_backlog') {
            // Create a new backlog task from the skipped planning task
            $newBacklogTask = new \App\Models\Task([
                'title' => $planning_task->title.' (Opnieuw)',
                'description' => $this->appendSkipHistory($planning_task, $planning_task->description),
                'location_id' => $planning_task->task->location_id ??
                               $planning_task->location_id ??
                               $planning_task->planning->locations()->first()?->id,
                'status' => TaskStatus::OPEN,
                'priority' => $planning_task->task->priority ?? TaskPriority::NORMAL,
                'estimated_time_minutes' => $planning_task->estimated_time_minutes ?? $planning_task->task?->estimated_time_minutes,
                'created_by' => Auth::id(),
            ]);
            $newBacklogTask->save();

            $message = 'Overgeslagen taak opnieuw toegevoegd aan backlog als nieuwe taak.';
        } else {
            $message = 'Overgeslagen taak gemarkeerd als niet opnieuw toe te voegen.';
        }

        // Update the latest skip completion with review information
        $skipCompletion = $planning_task->completions()
            ->where('review_outcome', 'skipped')
            ->latest()
            ->first();

        if ($skipCompletion) {
            $skipCompletion->update([
                'review_notes' => $reviewNotes,
                'reviewed_at' => now(),
                'reviewed_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }

        // Update planning task status to indicate it has been reviewed
        $planning_task->update([
            'status' => $action === 'add_to_backlog' ? TaskStatus::OPEN : TaskStatus::COMPLETED,
        ]);

        // If it's a backlog task, also update the main task status
        if ($planning_task->task) {
            $planning_task->task->update([
                'status' => $action === 'add_to_backlog' ? TaskStatus::OPEN : TaskStatus::COMPLETED,
            ]);
        }

        return redirect()->route('admin.tasks.review')->with('success', $message);
    }

    /**
     * Helper to append skip history to a task description.
     */
    private function appendSkipHistory(PlanningTask $planning_task, ?string $existing_description): string
    {
        $skipCompletion = $planning_task->completions()
            ->where('review_outcome', 'skipped')
            ->latest()
            ->first();

        $history = "\n\n--- OPNIEUW IN DE PLANNING ---\n";
        $history .= "Oorspronkelijke planning: {$planning_task->planning->title}\n";

        if ($skipCompletion) {
            $history .= 'Overgeslagen op: '.$skipCompletion->created_at->format('d-m-Y H:i')."\n";
            $history .= 'Overgeslagen door: '.($skipCompletion->user->name ?? 'Onbekend')."\n";
            if ($skipCompletion->comment) {
                $history .= "Reden: {$skipCompletion->comment}\n";
            }
        }

        return ($existing_description ?? '').$history;
    }
}
