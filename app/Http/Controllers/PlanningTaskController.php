<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Events\LocationCompleted;
use App\Events\TaskReadyForReview;
use App\Models\Planning;
use App\Models\PlanningComment;
use App\Models\PlanningTask;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
// For manual validation if needed
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanningTaskController extends Controller
{
    private function syncLinkedVehicleTaskStatus(PlanningTask $planningTask, TaskStatus $status): void
    {
        try {
            if ($planningTask->is_vehicle_task && $planningTask->vehicleTask) {
                $planningTask->vehicleTask->update(['status' => $status]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed syncing vehicle task status: '.$e->getMessage());
        }
    }
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

        return view('plannings.tasks.show', compact('planning_task'));
    }

    /**
     * Mark a planning task as completed.
     */
    public function complete(Request $request, Planning $planning, PlanningTask $planning_task, ImageService $imageService): RedirectResponse
    {
        if ($planning_task->planning_id !== $planning->id) {
            abort(404);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $validationRules = [
            'completed_notes' => 'required|string|max:65535',
            'is_fully_completed' => 'required|boolean',
        ];

        // Only require photos for non-admin users
        if (!$user || !$user->isAdmin()) {
            $validationRules['photos'] = 'required|array|min:1';
            $validationRules['photos.*'] = 'image|mimes:jpeg,png,jpg,webp,gif|max:20480'; // Max 20MB - will be compressed to 2MB
        } else {
            // For admins, photos are optional, but if provided, they must be valid
            $validationRules['photos'] = 'nullable|array';
            $validationRules['photos.*'] = 'image|mimes:jpeg,png,jpg,webp,gif|max:20480'; // Max 20MB - will be compressed to 2MB
        }

        $request->validate($validationRules);

        $isFullyCompleted = $request->boolean('is_fully_completed');

        // Create the completion record
        $completion = $planning_task->completions()->create([
            'user_id' => $user->id,
            'comment' => $request->input('completed_notes'),
            'is_fully_completed' => $isFullyCompleted,
        ]);

        // Copy photos from the previous completion if it exists, so they are not lost
        $previousCompletion = $planning_task->completions()
            ->where('id', '!=', $completion->id)
            ->where('review_outcome', '!=', 'reopened')
            ->latest()
            ->first();

        if ($previousCompletion) {
            foreach ($previousCompletion->photos as $oldPhoto) {
                $completion->photos()->create([
                    'file_path' => $oldPhoto->file_path,
                ]);
            }
        }

        // Store photos for the completion, if any were uploaded
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                try {
                    // Compress and save the image
                    $filename = uniqid('ptc_'.$completion->id.'_', true).'.'.$photo->getClientOriginalExtension();
                    $path = $imageService->saveCompressedImage(
                        $photo,
                        'planning-task-completion-photos/'.$completion->id,
                        $filename,
                        'public'
                    );
                    $completion->photos()->create(['file_path' => $path]);
                } catch (\Exception $e) {
                    Log::error('Error compressing image: '.$e->getMessage());
                    // Fallback to original method if compression fails
                    $path = $photo->store('planning-task-completion-photos/'.$completion->id, 'public');
                    $completion->photos()->create(['file_path' => $path]);
                }
            }
        }

        $newStatus = $user && $user->isAdmin() ? TaskStatus::COMPLETED : TaskStatus::REVIEW;
        $planning_task->update([
            'completed_at' => now(),
            'completed_notes' => $request->input('completed_notes'), // Keep last note for easy access
            'status' => $newStatus,
        ]);

        // Sync vehicle task status when applicable
        $this->syncLinkedVehicleTaskStatus($planning_task, $newStatus);

        // If it's a backlog task, also update the main task's status
        if ($planning_task->task) {
            $planning_task->task->update(['status' => $newStatus]);

            if ($newStatus === TaskStatus::REVIEW) {
                event(new TaskReadyForReview($planning_task->task));
            }
        }

        $message = $isFullyCompleted ? "Taak '{$planning_task->title}' gemarkeerd voor review." : "Voltooiingspoging voor '{$planning_task->title}' genoteerd en voor review aangeboden.";
        if ($user && $user->isAdmin()) {
            $message = "Taak '{$planning_task->title}' als voltooid gemarkeerd.";
        }

        $planning->checkAndUpdateStatus();

        return redirect()->route('plannings.show', $planning)->with('success', $message);
    }

    /**
     * Mark a planning task as not completed.
     */
    public function uncomplete(Request $request, Planning $planning, PlanningTask $planning_task): RedirectResponse
    {
        if ($planning_task->planning_id !== $planning->id) {
            abort(404);
        }

        // Admins must provide a reason for reopening
        /** @var \App\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user->isAdmin()) {
            $request->validate([
                'rejection_reason' => 'required|string|max:65535',
            ]);

            // Create a new completion record to log the reopening event
            $planning_task->completions()->create([
                'user_id' => $user->id,
                'comment' => 'Taak heropend door admin.',
                'is_fully_completed' => false,
                'review_notes' => $request->input('rejection_reason'),
                'reviewed_at' => now(),
                'review_outcome' => 'reopened',
                'reviewed_by' => $user->id,
            ]);
        }

        $planning_task->update([
            'completed_at' => null,
            'completed_notes' => null, // Also clear notes when uncompleting
            'status' => TaskStatus::OPEN, // Explicitly set the status back to open
        ]);

        // Sync linked vehicle task if applicable
        $this->syncLinkedVehicleTaskStatus($planning_task, TaskStatus::OPEN);

        // Also update the original task's status
        if ($planning_task->task) {
            $planning_task->task->update(['status' => TaskStatus::OPEN]);
        }

        $planning->checkAndUpdateStatus();

        return redirect()->route('plannings.show', $planning)->with('success', "Taak '{$planning_task->title}' als openstaand gemarkeerd.");
    }

    // Toekomstige methode voor het web toevoegen van foto's (indien nodig)
    // public function storePhoto(Request $request, Planning $planning, PlanningTask $planning_task): RedirectResponse
    // {
    //     if ($planning_task->planning_id !== $planning->id) {
    //         abort(404);
    //     }

    //     $request->validate([
    //         'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048', // Max 2MB
    //     ]);

    //     if ($request->hasFile('photo')) {
    //         $file = $request->file('photo');
    //         $originalName = $file->getClientOriginalName();
    //         $path = $file->store('planning-task-photos/' . $planning_task->id, 'public');

    //         $planning_task->planningTaskPhotos()->create([
    //             'path' => $path,
    //             'original_name' => $originalName,
    //             'mime_type' => $file->getMimeType(),
    //             'size' => $file->getSize(),
    //         ]);

    //         return redirect()->route('plannings.show', $planning)->with('success', 'Foto succesvol toegevoegd aan taak.');
    //     }

    //     return redirect()->route('plannings.show', $planning)->with('error', 'Kon foto niet uploaden.');
    // }

    public function approve(Request $request, PlanningTask $planning_task): RedirectResponse
    {
        $planning_task->update(['status' => TaskStatus::COMPLETED]);

        // Sync linked vehicle task if applicable
        $this->syncLinkedVehicleTaskStatus($planning_task, TaskStatus::COMPLETED);

        // Add review notes to the latest completion
        if ($latest_completion = $planning_task->completions()->latest()->first()) {
            $latest_completion->update([
                'review_notes' => $request->input('review_notes'),
                'reviewed_at' => now(),
                'review_outcome' => 'approved',
                'reviewed_by' => Auth::id(),
            ]);
        }

        // If it's a backlog task, also update the main task's status
        if ($task = $planning_task->task) {
            $task->update(['status' => TaskStatus::COMPLETED]);
        }

        $planning_task->planning->checkAndUpdateStatus();

        // Check if this location is now completed and notify if needed
        $this->checkLocationCompletionAndNotify($planning_task);

        $message = 'Geplande taak goedgekeurd.';

        // Async path: return JSON when requested
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => $message,
                'planning_task_id' => $planning_task->id,
                'planning_id' => $planning_task->planning_id,
                'new_status' => TaskStatus::COMPLETED->value,
            ]);
        }

        // Conditional redirect: if coming from a planning overview, go back there
        if ($request->filled('planning_id')) {
            $planning = $planning_task->planning;
            return redirect()->route('plannings.show', $planning)->with('success', $message);
        }

        return redirect()->route('plannings.review')->with('success', $message);
    }

    public function reject(Request $request, PlanningTask $planning_task): RedirectResponse
    {
        // Require a reason for rejection
        $request->validate([
            'review_notes' => ['required', 'string', 'min:3'],
            'create_replacement' => ['nullable'],
        ]);

        $createReplacement = $request->boolean('create_replacement', false);

        // Track a newly created backlog task ID (if any) so we can redirect to it explicitly
        $newTaskId = null;

        DB::transaction(function () use ($request, $planning_task, $createReplacement, &$newTaskId) {
            // Step 1: Update statuses of the original planning task and backlog task to 'rejected'
            $planning_task->update([
                'status' => TaskStatus::REJECTED,
                'completed_at' => null, // A rejected task is not considered completed
            ]);

            // Sync linked vehicle task if applicable
            $this->syncLinkedVehicleTaskStatus($planning_task, TaskStatus::REJECTED);

            // --- Main Rejection Logic ---
            if ($createReplacement) {
                $reason = (string) $request->input('review_notes');
                $prependReason = function (?string $existing) use ($reason) {
                    $base = $existing ? ($existing . "\n\n") : '';
                    return $base . "Reden afwijzing: " . $reason;
                };

                if (!is_null($planning_task->task_id) && $planning_task->task) {
                    // CASE 1: The rejected task is a properly linked BACKLOG task. Replicate it.
                    $original_task = $planning_task->task;
                    $original_task->update(['status' => TaskStatus::REJECTED]);

                    // Create a new 'V2' backlog task by replicating the original
                    $new_task = $original_task->replicate();
                    $new_task->status = TaskStatus::OPEN;
                    $new_task->title = $original_task->title . ' (Herstel)';
                    $new_task->created_at = now();
                    $new_task->updated_at = now();
                    // Add reason and history to description
                    $withReason = $prependReason($new_task->description);
                    $new_task->description = $this->appendCompletionHistory($planning_task, $withReason);
                    $new_task->estimated_time_minutes = $original_task->estimated_time_minutes;
                    $new_task->deadline = $original_task->deadline;
                    $new_task->save();

                    // Remember for redirect after transaction
                    $newTaskId = $new_task->id;

                    // Copy photos from the latest completion into the new backlog task
                    if ($latest_completion = $planning_task->completions()->latest()->first()) {
                        foreach ($latest_completion->photos as $photo) {
                            $new_task->taskPhotos()->create([
                                'file_path' => $photo->file_path,
                                'uploaded_at' => now(),
                            ]);
                        }
                    }
                    // Also carry over any photos attached to the original backlog task itself
                    foreach ($original_task->taskPhotos as $taskPhoto) {
                        $new_task->taskPhotos()->create([
                            'file_path' => $taskPhoto->file_path,
                            'uploaded_at' => now(),
                        ]);
                    }
                } else {
                    // CASE 2: DEFAULT task or broken link: create a NEW backlog task from PlanningTask data.
                    $descriptionWithReason = $prependReason($planning_task->description);
                    $descriptionFull = $this->appendCompletionHistory($planning_task, $descriptionWithReason);

                    $new_backlog_task = new \App\Models\Task([
                        'title' => $planning_task->title . ' (Herstel)',
                        'description' => $descriptionFull,
                        'location_id' => $planning_task->location_id ?? $planning_task->planning->locations()->first()->id,
                        'status' => TaskStatus::OPEN,
                        'priority' => \App\Enums\TaskPriority::NORMAL,
                        'estimated_time_minutes' => $planning_task->estimated_time_minutes,
                        'created_by' => Auth::id(),
                    ]);
                    $new_backlog_task->save();

                    // Remember for redirect after transaction
                    $newTaskId = $new_backlog_task->id;

                    // Copy photos from the latest completion into the new backlog task
                    if ($latest_completion = $planning_task->completions()->latest()->first()) {
                        foreach ($latest_completion->photos as $photo) {
                            $new_backlog_task->taskPhotos()->create([
                                'file_path' => $photo->file_path,
                                'uploaded_at' => now(),
                            ]);
                        }
                    }
                }
            } else {
                // No replacement requested: mark original task (if any) as rejected as well
                if (!is_null($planning_task->task_id) && $planning_task->task) {
                    $planning_task->task->update(['status' => TaskStatus::REJECTED]);
                }
            }
            // --- End Main Rejection Logic ---

            // Step 3: Log the rejection details on the completion record for traceability
            if ($latest_completion = $planning_task->completions()->latest()->first()) {
                $latest_completion->update([
                    'review_notes' => $request->input('review_notes'),
                    'reviewed_at' => now(),
                    'review_outcome' => 'rejected',
                    'reviewed_by' => Auth::id(),
                ]);
            }
        });

        $message = $request->boolean('create_replacement')
            ? 'Taak afgekeurd. Een nieuwe taak is aangemaakt in de backlog.'
            : 'Taak afgekeurd. Er is geen nieuwe taak aangemaakt.';

        // Enforce planning status gating after rejection
        $planning_task->planning->checkAndUpdateStatus();

        // Async path: return JSON when requested
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => $message,
                'planning_task_id' => $planning_task->id,
                'planning_id' => $planning_task->planning_id,
                'new_status' => TaskStatus::REJECTED->value,
                'replacement_created' => (bool) $request->boolean('create_replacement'),
            ]);
        }

        // Preferred redirect: if coming from a planning overview, always go back there
        if ($request->filled('planning_id')) {
            $planning = $planning_task->planning;
            return redirect()->route('plannings.show', $planning)
                ->with('success', $message);
        }

        return redirect()->route('plannings.review')->with('success', $message);
    }

    /**
     * Helper to append completion history to a task description.
     */
    private function appendCompletionHistory(PlanningTask $planning_task, ?string $existing_description): string
    {
        $history = "--- Vorige pogingen (meest recent eerst) ---\n\n";
        $completions = $planning_task->completions()->with('user')->orderBy('created_at', 'desc')->get();

        if ($completions->isEmpty()) {
            return $existing_description ?? '';
        }

        foreach ($completions as $completion) {
            $outcome = $completion->review_outcome ? " -> Oordeel: " . ucfirst($completion->review_outcome) : '';
            $history .= "----------------------------------------\n";
            $history .= "Datum: " . $completion->created_at->format('d-m-Y H:i') . "\n";
            $history .= "Gebruiker: " . ($completion->user->name ?? 'Onbekend') . "\n";
            $history .= "Notities: " . ($completion->comment ?? 'Geen notities.') . "\n";
            if ($completion->review_notes) {
                $history .= "Review Notities: " . $completion->review_notes . $outcome . "\n";
            }
        }

        return ($existing_description ? $existing_description . "\n\n" : '') . $history;
    }

    /**
     * Mark a planning task as completed via a simple UI action.
     */
    public function simpleComplete(Request $request, Planning $planning, PlanningTask $planning_task)
    {
        if ($planning_task->planning_id !== $planning->id) {
            abort(404);
        }

        $planning_task->update([
            'completed_at' => now(),
            'status' => TaskStatus::COMPLETED,
        ]);

        // Sync linked vehicle task if applicable
        $this->syncLinkedVehicleTaskStatus($planning_task, TaskStatus::COMPLETED);

        // Keep linked backlog task in sync as well
        if ($planning_task->task) {
            $planning_task->task->update(['status' => TaskStatus::COMPLETED]);
        }

        $planning->checkAndUpdateStatus();

        // Check if this location is now completed and notify if needed
        $this->checkLocationCompletionAndNotify($planning_task);

        return response()->json(['task' => $planning_task]);
    }

    /**
     * Mark a planning task as not completed via a simple UI action.
     */
    public function simpleUncomplete(Request $request, Planning $planning, PlanningTask $planning_task)
    {
        if ($planning_task->planning_id !== $planning->id) {
            abort(404);
        }

        $planning_task->update([
            'completed_at' => null,
            'status' => TaskStatus::OPEN,
        ]);

        // Sync linked vehicle task if applicable
        $this->syncLinkedVehicleTaskStatus($planning_task, TaskStatus::OPEN);

        if ($planning_task->task) {
            $planning_task->task->update(['status' => TaskStatus::OPEN]);
        }

        $planning->checkAndUpdateStatus();

        return response()->json(['task' => $planning_task]);
    }

    /**
     * Submit completion details (notes, photos) from the step-by-step view.
     */
    public function submitCompletion(Request $request, Planning $planning, PlanningTask $planning_task, ImageService $imageService)
    {
        if ($planning_task->planning_id !== $planning->id) {
            abort(404);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'completed_notes' => 'required|string|max:65535',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp,gif|max:20480', // Max 20MB - will be compressed to 2MB
        ]);

        // Backend validation for is_photo_required
        $isPhotoRequired = (bool) ($planning_task->task?->is_photo_required ?? $planning_task->defaultTask?->is_photo_required ?? false);
        if ($isPhotoRequired && !$request->hasFile('photos')) {
            // Check if there are existing photos (if it's being updated/re-submitted)
            $existingPhotosCount = $planning_task->completions()
                ->where('review_outcome', '!=', 'reopened')
                ->latest()
                ->first()?->photos()->count() ?? 0;

            if ($existingPhotosCount === 0) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['photos' => ['Foto is verplicht voor deze taak.']]
                ], 422);
            }
        }

        $isFullyCompleted = true; // Default to true now that the checkbox is removed

        // Create the completion record
        $completion = $planning_task->completions()->create([
            'user_id' => $user->id,
            'comment' => $request->input('completed_notes'),
            'is_fully_completed' => $isFullyCompleted,
            'task_duration_seconds' => $request->input('task_duration_seconds', 0),
        ]);

        // Copy photos from the previous completion if it exists, so they are not lost
        $previousCompletion = $planning_task->completions()
            ->where('id', '!=', $completion->id)
            ->where('review_outcome', '!=', 'reopened')
            ->latest()
            ->first();

        if ($previousCompletion) {
            foreach ($previousCompletion->photos as $oldPhoto) {
                $completion->photos()->create([
                    'file_path' => $oldPhoto->file_path,
                ]);
            }
        }

        // Check if we have new photos
        if ($request->hasFile('photos')) {
            // Store photos for the completion
            foreach ($request->file('photos') as $photo) {
                try {
                    // Compress and save the image
                    $filename = uniqid('ptc_'.$completion->id.'_', true).'.'.$photo->getClientOriginalExtension();
                    $path = $imageService->saveCompressedImage(
                        $photo,
                        'planning-task-completion-photos/'.$completion->id,
                        $filename,
                        'public'
                    );
                    $completion->photos()->create(['file_path' => $path]);
                } catch (\Exception $e) {
                    Log::error('Error compressing image: '.$e->getMessage());
                    // Fallback to original method if compression fails
                    $path = $photo->store('planning-task-completion-photos/'.$completion->id, 'public');
                    $completion->photos()->create(['file_path' => $path]);
                }
            }
        }

        $newStatus = TaskStatus::REVIEW;
        $planning_task->update([
            'completed_at' => now(),
            'completed_notes' => $request->input('completed_notes'),
            'status' => $newStatus,
        ]);

        // Sync linked vehicle task if applicable
        $this->syncLinkedVehicleTaskStatus($planning_task, $newStatus);

        if ($planning_task->task) {
            $planning_task->task->update(['status' => $newStatus]);
        }

        $planning->checkAndUpdateStatus();

        // Check if this location is now completed and notify if needed
        $this->checkLocationCompletionAndNotify($planning_task);

        return response()->json(['task' => $planning_task->fresh()]);
    }

    /**
     * Skip a task with a reason.
     */
    public function skip(Request $request, Planning $planning, PlanningTask $planning_task, ImageService $imageService)
    {
        if ($planning_task->planning_id !== $planning->id) {
            abort(404);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'reason' => 'required|string|max:65535',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp,gif|max:20480', // Max 20MB - will be compressed to 2MB
        ]);

        // Create the completion record to log the skip event
        $completion = $planning_task->completions()->create([
            'user_id' => $user->id,
            'comment' => $request->input('reason'),
            'is_fully_completed' => false,
            'review_outcome' => 'skipped', // Using this field to denote a skip
            'task_duration_seconds' => $request->input('task_duration_seconds', 0),
        ]);

        // Store photos for the completion, if any were uploaded
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                try {
                    // Compress and save the image
                    $filename = uniqid('ptc_'.$completion->id.'_', true).'.'.$photo->getClientOriginalExtension();
                    $path = $imageService->saveCompressedImage(
                        $photo,
                        'planning-task-completion-photos/'.$completion->id,
                        $filename,
                        'public'
                    );
                    $completion->photos()->create(['file_path' => $path]);
                } catch (\Exception $e) {
                    Log::error('Error compressing image: '.$e->getMessage());
                    // Fallback to original method if compression fails
                    $path = $photo->store('planning-task-completion-photos/'.$completion->id, 'public');
                    $completion->photos()->create(['file_path' => $path]);
                }
            }
        }

        // Update task status to skipped
        $planning_task->update([
            'status' => TaskStatus::SKIPPED,
        ]);

        // Sync vehicle task status when applicable
        $this->syncLinkedVehicleTaskStatus($planning_task, TaskStatus::SKIPPED);

        if ($planning_task->task) {
            $planning_task->task->update(['status' => TaskStatus::SKIPPED]);
        }

        // Load completion with photos to get URLs
        $completion->load('photos');

        // Get the skip photos URLs
        $skipPhotos = $completion->photos->pluck('url')->toArray();

        // Check if this location is now completed and notify if needed
        $this->checkLocationCompletionAndNotify($planning_task);

        return response()->json([
            'task' => $planning_task->fresh(),
            'skip_photos' => $skipPhotos
        ]);
    }

    /**
     * Reopen a task from the step-by-step view if it's in review.
     */
    public function reopen(Request $request, Planning $planning, PlanningTask $planning_task)
    {
        if ($planning_task->planning_id !== $planning->id) {
            abort(404);
        }

        // Allow reopening if the task is in 'review', 'skipped' or 'rejected' state
        if (!in_array($planning_task->status, [TaskStatus::REVIEW, TaskStatus::SKIPPED, TaskStatus::REJECTED])) {
            return response()->json(['message' => 'Taak kan niet heropend worden.'], 403);
        }

        $planning_task->update([
            'completed_at' => null,
            'status' => TaskStatus::OPEN,
        ]);

        // Sync vehicle task status when applicable
        $this->syncLinkedVehicleTaskStatus($planning_task, TaskStatus::OPEN);

        if ($planning_task->task) {
            $planning_task->task->update(['status' => TaskStatus::OPEN]);
        }

        $planning->checkAndUpdateStatus();
        $planning_task->load(['completions.photos']);
        $latestCompletion = $planning_task->completions->where('review_outcome', '!=', 'reopened')->sortByDesc('created_at')->first();
        $photos = $latestCompletion ? $latestCompletion->photos->pluck('url')->toArray() : [];

        return response()->json([
            'task' => array_merge($planning_task->fresh()->toArray(), [
                'photos' => $photos
            ])
        ]);
    }

    /**
     * Store an extra task for a location.
     */
    public function storeExtraTask(Request $request, Planning $planning, $location_id, ImageService $imageService)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'notes' => 'required|string',
            'photos.*' => 'nullable|image|max:10240',
        ]);

        $comment = $planning->comments()->create([
            'location_id' => $location_id === 'backlog' ? null : $location_id,
            'user_id' => $user->id,
            'comment' => $validated['notes'],
        ]);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                try {
                    $filename = uniqid('pc_'.$comment->id.'_', true).'.'.$photo->getClientOriginalExtension();
                    $path = $imageService->saveCompressedImage(
                        $photo,
                        'planning-comment-photos/'.$comment->id,
                        $filename,
                        'public'
                    );
                    $comment->photos()->create(['file_path' => $path]);
                } catch (\Exception $e) {
                    Log::error('Error compressing image: '.$e->getMessage());
                    $path = $photo->store('planning-comment-photos/'.$comment->id, 'public');
                    $comment->photos()->create(['file_path' => $path]);
                }
            }
        }

        $comment->load('photos');

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'photos' => $comment->photos->pluck('url'),
                'location_id' => $comment->location_id,
                'user_id' => $comment->user_id,
                'created_at' => $comment->created_at->format('H:i'),
            ]
        ]);
    }

    /**
     * Update a planning comment.
     */
    public function updateComment(Request $request, PlanningComment $comment, ImageService $imageService)
    {
        $user = Auth::user();

        // Check if user is the owner of the comment or an admin
        if (!$user->isAdmin() && $comment->user_id !== $user->id) {
            abort(403, 'Je hebt geen toestemming om deze opmerking te wijzigen.');
        }

        $validated = $request->validate([
            'notes' => 'required|string',
            'photos.*' => 'nullable|image|max:10240',
        ]);

        $comment->update([
            'comment' => $validated['notes'],
        ]);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                try {
                    $filename = uniqid('pc_'.$comment->id.'_', true).'.'.$photo->getClientOriginalExtension();
                    $path = $imageService->saveCompressedImage(
                        $photo,
                        'planning-comment-photos/'.$comment->id,
                        $filename,
                        'public'
                    );
                    $comment->photos()->create(['file_path' => $path]);
                } catch (\Exception $e) {
                    Log::error('Error compressing image: '.$e->getMessage());
                    $path = $photo->store('planning-comment-photos/'.$comment->id, 'public');
                    $comment->photos()->create(['file_path' => $path]);
                }
            }
        }

        $comment->load('photos');

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'photos' => $comment->photos->pluck('url'),
                'location_id' => $comment->location_id,
                'user_id' => $comment->user_id,
                'created_at' => $comment->created_at->format('H:i'),
            ]
        ]);
    }

    /**
     * Check if a location is completed within a planning and trigger LocationCompleted event.
     *
     * @param \App\Models\PlanningTask $planningTask
     * @return void
     */
    private function checkLocationCompletionAndNotify(PlanningTask $planningTask): void
    {
        $planning = $planningTask->planning;

        // Determine the location for this task
        $location = null;
        if ($planningTask->location_id) {
            // Default task with direct location assignment
            $location = \App\Models\Location::find($planningTask->location_id);
        } elseif ($planningTask->task && $planningTask->task->location_id) {
            // Backlog task with location
            $location = $planningTask->task->location;
        }

        if (!$location) {
            return; // No location found, nothing to check
        }

        // Check if all tasks for this location in this planning are completed
        if ($location->areAllTasksCompletedInPlanning($planning)) {
            // Trigger the LocationCompleted event
            LocationCompleted::dispatch($location, $planning);
        }
    }
}
