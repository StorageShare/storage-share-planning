<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\PlanningCommentPhoto;
use App\Models\PlanningTask;
use App\Models\PlanningTaskCompletionPhoto;
use App\Models\PlanningTaskPhoto;
use App\Models\Task;
use App\Models\TaskCompletionPhoto;
use App\Models\TaskPhoto;
use App\Services\StorageShareApiService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TaskPhotoProcessController extends Controller
{
    public function __construct(
        private readonly StorageShareApiService $storageShareApi,
    ) {}

    /**
     * Start the photo distribution process.
     */
    public function distribute(Request $request, Task $task): RedirectResponse
    {
        if (! auth()->user()->canTriggerPhotoWorkflow()) {
            abort(403, 'U heeft geen toestemming om dit proces te starten.');
        }

        $request->validate([
            'room' => 'required|string',
        ]);

        $task->update([
            'room' => $request->room,
            'photo_process_step' => 'PHOTO_DISTRIBUTED',
            'photo_process_at' => now(),
        ]);

        $photo = $task->planningTasks->flatMap->planningTaskPhotos->sortByDesc('created_at')->first(fn ($p) => $p->room === $request->room);

        if (! $photo) {
            // Fallback: check completion photos too
            $photo = $task->planningTasks->flatMap->completions->flatMap->photos->sortByDesc('created_at')->first(fn ($p) => $p->room === $request->room);
        }

        if (! $photo) {
            // Fallback: just take the latest photo of this task if no specific room photo found
            $photo = $task->planningTasks->flatMap->planningTaskPhotos->sortByDesc('created_at')->first();
        }

        if (! $photo) {
            // Fallback: just take the latest completion photo of this task
            $photo = $task->planningTasks->flatMap->completions->flatMap->photos->sortByDesc('created_at')->first();
        }

        if (! $photo) {
            return back()->with('error', 'Geen foto gevonden voor deze taak om rond te sturen.');
        }

        // API connection with backend to send to all customers
        $result = $this->storageShareApi->distributePhotoForWorkflow([
            'space_id' => $task->location->external_id,
            'photo_url' => $photo->url ?? Storage::disk('public')->url($photo->file_path),
            'room_identifier' => $request->room,
            'planning_task_id' => $task->id,
            'follow_up' => [
                'first_in_days' => 7,
                'second_in_days' => 14,
            ],
        ]);

        if (! $result['success']) {
            return back()->with('error', $result['user_error']);
        }

        $task->update([
            'photo_process_notification_id' => $result['notification_id'] ?? null,
        ]);

        return back()->with('success', 'Foto is succesvol rondgestuurd naar alle huurders via de API.');
    }

    /**
     * Link a room and location to a specific photo.
     */
    public function linkRoomToPhoto(Request $request, PlanningTaskPhoto $photo): JsonResponse|RedirectResponse
    {
        return $this->updatePhotoRoom($request, $photo);
    }

    /**
     * Link a room and location to a specific task photo.
     */
    public function linkRoomToTaskPhoto(Request $request, TaskPhoto $photo): JsonResponse|RedirectResponse
    {
        return $this->updatePhotoRoom($request, $photo);
    }

    /**
     * Link a room and location to a specific completion photo.
     */
    public function linkRoomToCompletionPhoto(Request $request, TaskCompletionPhoto $photo): JsonResponse|RedirectResponse
    {
        return $this->updatePhotoRoom($request, $photo);
    }

    /**
     * Link a room and location to a specific planning completion photo.
     */
    public function linkRoomToPlanningCompletionPhoto(Request $request, PlanningTaskCompletionPhoto $photo): JsonResponse|RedirectResponse
    {
        return $this->updatePhotoRoom($request, $photo);
    }

    /**
     * Link a room and location to a specific planning comment photo.
     */
    public function linkRoomToCommentPhoto(Request $request, PlanningCommentPhoto $photo): JsonResponse|RedirectResponse
    {
        return $this->updatePhotoRoom($request, $photo);
    }

    private function updatePhotoRoom(Request $request, Model $photo): JsonResponse|RedirectResponse
    {
        if (! auth()->user()->canExecutePlannings()) {
            abort(403);
        }

        $request->validate([
            'room' => 'required|string',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $data = ['room' => $request->room];
        if ($request->has('location_id')) {
            $data['location_id'] = $request->location_id;
        }

        $photo->update($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Ruimte succesvol gekoppeld aan de foto.']);
        }

        return back()->with('success', 'Ruimte succesvol gekoppeld aan de foto.');
    }

    /**
     * Start the photo distribution process for an external room (no specific task model).
     */
    public function distributeExternal(Request $request, string $externalId): RedirectResponse
    {
        if (! auth()->user()->canTriggerPhotoWorkflow()) {
            abort(403, 'U heeft geen toestemming om dit proces te starten.');
        }

        $request->validate([
            'room' => 'required|string',
        ]);

        // Find the location
        $location = Location::where('external_id', $externalId)->first();
        if (! $location) {
            return back()->with('error', 'Locatie niet gevonden.');
        }

        // We need at least one photo URL to distribute.
        // We look for the latest completion photo for this room at this location.
        $photo = PlanningTaskCompletionPhoto::whereHas('planningTaskCompletion.planningTask', function ($query) use ($location, $request) {
            $query->where('location_id', $location->id)
                ->where('room_identifier', $request->room);
        })
            ->latest()
            ->first();

        if (! $photo) {
            // Try to find it in normal completion photos if room matches
            $photo = TaskCompletionPhoto::where('room', $request->room)
                ->whereHas('taskCompletion.task', function (Builder $query) use ($location) {
                    $query->where('location_id', $location->id);
                })
                ->latest()
                ->first();
        }

        if (! $photo) {
            return back()->with('error', 'Geen foto gevonden voor deze ruimte om rond te sturen.');
        }

        $result = $this->storageShareApi->distributePhotoForWorkflow([
            'space_id' => $externalId,
            'photo_url' => $photo->url,
            'room_identifier' => $request->room,
            'follow_up' => [
                'first_in_days' => 7,
                'second_in_days' => 14,
            ],
        ], 'PhotoProcess (External)');

        if (! $result['success']) {
            return back()->with('error', $result['user_error']);
        }

        return back()->with('success', 'Foto is succesvol rondgestuurd naar alle huurders via de API.');
    }

    /**
     * Start the photo distribution process for a planning comment photo.
     */
    public function distributeCommentPhoto(Request $request, PlanningCommentPhoto $photo): RedirectResponse
    {
        if (! auth()->user()->canTriggerPhotoWorkflow()) {
            abort(403, 'U heeft geen toestemming om dit proces te starten.');
        }

        $request->validate([
            'room' => 'required|string',
        ]);

        // Update photo room if it changed
        if ($photo->room !== $request->room) {
            $photo->update(['room' => $request->room]);
        }

        if (! $photo->location) {
            return back()->with('error', 'Deze foto heeft geen gekoppelde locatie.');
        }

        if (! $photo->location->external_id) {
            return back()->with('error', 'De gekoppelde locatie heeft geen extern ID.');
        }

        $result = $this->storageShareApi->distributePhotoForWorkflow([
            'space_id' => $photo->location->external_id,
            'photo_url' => $photo->url,
            'room_identifier' => $request->room,
            'planning_comment_photo_id' => $photo->id,
            'follow_up' => [
                'first_in_days' => 7,
                'second_in_days' => 14,
            ],
        ], 'PhotoProcess (Comment)');

        if (! $result['success']) {
            return back()->with('error', $result['user_error']);
        }

        return back()->with('success', 'Foto is succesvol rondgestuurd naar alle huurders via de API.');
    }

    /**
     * Start the photo distribution process for a planning task (used for inactive rooms).
     */
    public function distributePlanningTask(Request $request, PlanningTask $planningTask): RedirectResponse
    {
        if (! auth()->user()->canTriggerPhotoWorkflow()) {
            abort(403, 'U heeft geen toestemming om dit proces te starten.');
        }

        $request->validate([
            'room' => 'required|string',
        ]);

        // Update room if it changed
        if ($planningTask->room_identifier !== $request->room) {
            $planningTask->update(['room_identifier' => $request->room]);
        }

        $location = $planningTask->location ?: $planningTask->specificLocation;
        if (! $location || ! $location->external_id) {
            return back()->with('error', 'Geen locatie gevonden voor deze taak of locatie heeft geen extern ID.');
        }

        // Look for the photo in completions
        $photo = $planningTask->completions->flatMap->photos
            ->sortByDesc('created_at')
            ->first(fn ($p) => $p->room === $request->room);

        if (! $photo) {
            $photo = $planningTask->completions->flatMap->photos
                ->sortByDesc('created_at')
                ->first();
        }

        if (! $photo) {
            return back()->with('error', 'Geen foto gevonden voor deze ruimte om rond te sturen.');
        }

        $result = $this->storageShareApi->distributePhotoForWorkflow([
            'space_id' => $location->external_id,
            'photo_url' => $photo->url,
            'room_identifier' => $request->room,
            'planning_task_id' => $planningTask->id,
            'follow_up' => [
                'first_in_days' => 7,
                'second_in_days' => 14,
            ],
        ], 'PhotoProcess (PlanningTask)');

        if (! $result['success']) {
            return back()->with('error', $result['user_error']);
        }

        return back()->with('success', 'Foto is succesvol rondgestuurd naar alle huurders via de API.');
    }

    /**
     * Get rooms for the location via storage-share-api.
     */
    public function getRooms(Task $task): JsonResponse
    {
        return $this->getRoomsByLocation($task->location);
    }

    /**
     * Get rooms for the location via storage-share-api.
     */
    public function getRoomsByLocation(Location $location): JsonResponse
    {
        $externalId = $location->external_id;

        if (! $externalId) {
            return response()->json(['success' => false, 'message' => 'Locatie heeft geen extern ID.'], 400);
        }

        $result = $this->storageShareApi->getSpaceRooms((string) $externalId);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status_code']);
        }

        return response()->json([
            'success' => true,
            'rooms' => $result['rooms'],
        ]);
    }

    /**
     * Create a task for sticking a sticker.
     */
    public function createStickerTask(Request $request, Task $task): View
    {
        $newTask = Task::create([
            'location_id' => $task->location_id,
            'title' => 'Sticker plakken op deur van ruimte '.$task->room,
            'description' => 'Er is geen reactie gekomen op het rondsturen van de foto van ruimte '.$task->room.'. Plak een sticker op de deur.',
            'status' => TaskStatus::OPEN,
            'priority' => TaskPriority::NORMAL,
            'room' => $task->room,
            'created_by' => auth()->id(),
            // Link back to this process if needed, or just start a new process tracking
            'photo_process_step' => 'STICKER_TASK_CREATED',
            'photo_process_at' => now(),
        ]);

        // Mark the original task process as continued
        $task->update(['photo_process_step' => 'STICKER_TASK_CREATED']);

        // Notify API that a sticker task has been created
        if ($task->photo_process_notification_id) {
            $this->storageShareApi->notifyStickerPlanned((string) $task->photo_process_notification_id, [
                'planning_task_id' => $newTask->id,
                'resend_in_days' => 14,
            ]);
        }

        return view('photo-workflow.task-created', ['task' => $newTask]);
    }

    /**
     * Create a task for taking a new photo.
     */
    public function createNewPhotoTask(Task $task): View
    {
        $newTask = Task::create([
            'location_id' => $task->location_id,
            'title' => 'Nieuwe foto maken van ruimte '.$task->room,
            'description' => '2 weken na het plakken van de sticker nog geen reactie. Maak een nieuwe foto van de ruimte.',
            'status' => TaskStatus::OPEN,
            'priority' => TaskPriority::NORMAL,
            'room' => $task->room,
            'created_by' => auth()->id(),
            'is_photo_required' => true,
            'photo_process_step' => 'SECOND_PHOTO_TASK_CREATED',
            'photo_process_at' => now(),
        ]);

        $task->update(['photo_process_step' => 'SECOND_PHOTO_TASK_CREATED']);

        return view('photo-workflow.task-created', ['task' => $newTask]);
    }

    /**
     * Create a task for evacuation.
     */
    public function createEvacuationTask(Task $task): View
    {
        $newTask = Task::create([
            'location_id' => $task->location_id,
            'title' => 'ONTRAUMING: Ruimte '.$task->room,
            'description' => 'Geen enkele reactie na foto, sticker en tweede foto. Ruimte ontruimen.',
            'status' => TaskStatus::OPEN,
            'priority' => TaskPriority::HIGH,
            'room' => $task->room,
            'created_by' => auth()->id(),
            'photo_process_step' => 'EVACUATION_TASK_CREATED',
            'photo_process_at' => now(),
        ]);

        $task->update(['photo_process_step' => 'EVACUATION_TASK_CREATED']);

        return view('photo-workflow.task-created', ['task' => $newTask]);
    }
}
