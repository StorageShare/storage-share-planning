<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\PlanningTask;
use App\Models\PlanningTaskPhoto;
use App\Models\Task;
use App\Models\TaskCompletionPhoto;
use App\Models\PlanningTaskCompletionPhoto;
use App\Models\TaskPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TaskPhotoProcessController extends Controller
{
    /**
     * Start the photo distribution process.
     */
    public function distribute(Request $request, Task $task): \Illuminate\Http\RedirectResponse
    {
        if (!auth()->user()->canTriggerPhotoWorkflow()) {
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

        $photo = $task->planningTasks->flatMap->planningTaskPhotos->sortByDesc('created_at')->first(fn($p) => $p->room === $request->room);

        if (!$photo) {
            // Fallback: check completion photos too
            $photo = $task->planningTasks->flatMap->completions->flatMap->photos->sortByDesc('created_at')->first(fn($p) => $p->room === $request->room);
        }

        if (!$photo) {
            // Fallback: just take the latest photo of this task if no specific room photo found
            $photo = $task->planningTasks->flatMap->planningTaskPhotos->sortByDesc('created_at')->first();
        }

        if (!$photo) {
            // Fallback: just take the latest completion photo of this task
            $photo = $task->planningTasks->flatMap->completions->flatMap->photos->sortByDesc('created_at')->first();
        }

        if (!$photo) {
            return back()->with('error', 'Geen foto gevonden voor deze taak om rond te sturen.');
        }

        // API connection with backend to send to all customers
        $apiUrl = config('services.storage_share_api.url') . '/photo-process/distribute';
        $apiToken = config('services.storage_share_api.token');

        try {
            $response = Http::withToken($apiToken)
                ->post($apiUrl, [
                    'space_id' => $task->location->external_id,
                    'photo_url' => $photo->url ?? Storage::disk('public')->url($photo->file_path),
                    'room_identifier' => $request->room,
                    'planning_task_id' => $task->id,
                    'follow_up' => [
                        'first_in_days' => 7,
                        'second_in_days' => 14,
                    ],
                ]);

            if ($response->successful()) {
                $notificationId = $response->json('notification_id');
                $task->update([
                    'photo_process_notification_id' => $notificationId,
                ]);
            } else {
                Log::error('PhotoProcess: API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return back()->with('error', 'Er is iets misgegaan bij het aanroepen van de API: ' . $response->json('message', 'Onbekende fout'));
            }
        } catch (\Exception $e) {
            Log::error('PhotoProcess: API connection error', [
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Kon geen verbinding maken met de API.');
        }

        return back()->with('success', 'Foto is succesvol rondgestuurd naar alle huurders via de API.');
    }

    /**
     * Link a room and location to a specific photo.
     */
    public function linkRoomToPhoto(Request $request, PlanningTaskPhoto $photo): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (!auth()->user()->canExecutePlannings()) {
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
     * Link a room and location to a specific task photo.
     */
    public function linkRoomToTaskPhoto(Request $request, TaskPhoto $photo): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (!auth()->user()->canExecutePlannings()) {
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
     * Link a room and location to a specific completion photo.
     */
    public function linkRoomToCompletionPhoto(Request $request, TaskCompletionPhoto $photo): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (!auth()->user()->canExecutePlannings()) {
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
     * Link a room and location to a specific planning completion photo.
     */
    public function linkRoomToPlanningCompletionPhoto(Request $request, PlanningTaskCompletionPhoto $photo): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (!auth()->user()->canExecutePlannings()) {
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
     * Link a room and location to a specific planning comment photo.
     */
    public function linkRoomToCommentPhoto(Request $request, \App\Models\PlanningCommentPhoto $photo): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (!auth()->user()->canExecutePlannings()) {
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
    public function distributeExternal(Request $request, string $externalId): \Illuminate\Http\RedirectResponse
    {
        if (!auth()->user()->canTriggerPhotoWorkflow()) {
            abort(403, 'U heeft geen toestemming om dit proces te starten.');
        }

        $request->validate([
            'room' => 'required|string',
        ]);

        // Find the location
        $location = Location::where('external_id', $externalId)->first();
        if (!$location) {
            return back()->with('error', 'Locatie niet gevonden.');
        }

        // We need at least one photo URL to distribute.
        // We look for the latest completion photo for this room at this location.
        $photo = \App\Models\PlanningTaskCompletionPhoto::whereHas('planningTaskCompletion.planningTask', function ($query) use ($location, $request) {
                $query->where('location_id', $location->id)
                      ->where('room_identifier', $request->room);
            })
            ->latest()
            ->first();

        if (!$photo) {
            // Try to find it in normal completion photos if room matches
            $photo = \App\Models\TaskCompletionPhoto::where('room', $request->room)
                ->whereHas('taskCompletion.task', function (\Illuminate\Contracts\Database\Eloquent\Builder $query) use ($location) {
                    $query->where('location_id', $location->id);
                })
                ->latest()
                ->first();
        }

        if (!$photo) {
            return back()->with('error', 'Geen foto gevonden voor deze ruimte om rond te sturen.');
        }

        // API connection with backend to send to all customers
        $apiUrl = config('services.storage_share_api.url') . '/photo-process/distribute';
        $apiToken = config('services.storage_share_api.token');

        try {
            $response = Http::withToken($apiToken)
                ->post($apiUrl, [
                    'space_id' => $externalId,
                    'photo_url' => $photo->url,
                    'room_identifier' => $request->room,
                    'follow_up' => [
                        'first_in_days' => 7,
                        'second_in_days' => 14,
                    ],
                ]);

            if ($response->successful()) {
                return back()->with('success', 'Foto is succesvol rondgestuurd naar alle huurders via de API.');
            } else {
                Log::error('PhotoProcess (External): API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return back()->with('error', 'Er is iets misgegaan bij het aanroepen van de API: ' . $response->json('message', 'Onbekende fout'));
            }
        } catch (\Exception $e) {
            Log::error('PhotoProcess (External): API connection error', [
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Kon geen verbinding maken met de API.');
        }
    }

    /**
     * Start the photo distribution process for a planning comment photo.
     */
    public function distributeCommentPhoto(Request $request, \App\Models\PlanningCommentPhoto $photo): \Illuminate\Http\RedirectResponse
    {
        if (!auth()->user()->canTriggerPhotoWorkflow()) {
            abort(403, 'U heeft geen toestemming om dit proces te starten.');
        }

        $request->validate([
            'room' => 'required|string',
        ]);

        // Update photo room if it changed
        if ($photo->room !== $request->room) {
            $photo->update(['room' => $request->room]);
        }

        if (!$photo->location) {
            return back()->with('error', 'Deze foto heeft geen gekoppelde locatie.');
        }

        if (!$photo->location->external_id) {
            return back()->with('error', 'De gekoppelde locatie heeft geen extern ID.');
        }

        // API connection with backend to send to all customers
        $apiUrl = config('services.storage_share_api.url') . '/photo-process/distribute';
        $apiToken = config('services.storage_share_api.token');

        try {
            $response = Http::withToken($apiToken)
                ->post($apiUrl, [
                    'space_id' => $photo->location->external_id,
                    'photo_url' => $photo->url,
                    'room_identifier' => $request->room,
                    // We don't have a specific task_id here, but we can pass the photo ID
                    'planning_comment_photo_id' => $photo->id,
                    'follow_up' => [
                        'first_in_days' => 7,
                        'second_in_days' => 14,
                    ],
                ]);

            if ($response->successful()) {
                return back()->with('success', 'Foto is succesvol rondgestuurd naar alle huurders via de API.');
            } else {
                Log::error('PhotoProcess (Comment): API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return back()->with('error', 'Er is iets misgegaan bij het aanroepen van de API: ' . $response->json('message', 'Onbekende fout'));
            }
        } catch (\Exception $e) {
            Log::error('PhotoProcess (Comment): API connection error', [
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Kon geen verbinding maken met de API.');
        }
    }

    /**
     * Start the photo distribution process for a planning task (used for inactive rooms).
     */
    public function distributePlanningTask(Request $request, PlanningTask $planningTask): \Illuminate\Http\RedirectResponse
    {
        if (!auth()->user()->canTriggerPhotoWorkflow()) {
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
        if (!$location || !$location->external_id) {
            return back()->with('error', 'Geen locatie gevonden voor deze taak of locatie heeft geen extern ID.');
        }

        // Look for the photo in completions
        $photo = $planningTask->completions->flatMap->photos
            ->sortByDesc('created_at')
            ->first(fn($p) => $p->room === $request->room);

        if (!$photo) {
            $photo = $planningTask->completions->flatMap->photos
                ->sortByDesc('created_at')
                ->first();
        }

        if (!$photo) {
            return back()->with('error', 'Geen foto gevonden voor deze ruimte om rond te sturen.');
        }

        // API connection with backend to send to all customers
        $apiUrl = config('services.storage_share_api.url') . '/photo-process/distribute';
        $apiToken = config('services.storage_share_api.token');

        try {
            $response = Http::withToken($apiToken)
                ->post($apiUrl, [
                    'space_id' => $location->external_id,
                    'photo_url' => $photo->url,
                    'room_identifier' => $request->room,
                    'planning_task_id' => $planningTask->id,
                    'follow_up' => [
                        'first_in_days' => 7,
                        'second_in_days' => 14,
                    ],
                ]);

            if ($response->successful()) {
                return back()->with('success', 'Foto is succesvol rondgestuurd naar alle huurders via de API.');
            } else {
                Log::error('PhotoProcess (PlanningTask): API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return back()->with('error', 'Er is iets misgegaan bij het aanroepen van de API: ' . $response->json('message', 'Onbekende fout'));
            }
        } catch (\Exception $e) {
            Log::error('PhotoProcess (PlanningTask): API connection error', [
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Kon geen verbinding maken met de API.');
        }
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

        if (!$externalId) {
            return response()->json(['success' => false, 'message' => 'Locatie heeft geen extern ID.'], 400);
        }

        $apiUrl = config('services.storage_share_api.url') . '/spaces/' . $externalId . '/rooms';
        $apiToken = config('services.storage_share_api.token');

        try {
            $response = Http::withToken($apiToken)->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['success']) && $data['success'] && isset($data['rooms'])) {
                    return response()->json([
                        'success' => true,
                        'rooms' => $data['rooms']
                    ]);
                }
                return response()->json($data);
            }

            return response()->json([
                'success' => false,
                'message' => 'API fout: ' . $response->status()
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('PhotoProcess: API error fetching rooms', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Fout bij verbinden met API.'], 500);
        }
    }

    /**
     * Create a task for sticking a sticker.
     */
    public function createStickerTask(Request $request, Task $task): \Illuminate\View\View
    {
        $newTask = Task::create([
            'location_id' => $task->location_id,
            'title' => 'Sticker plakken op deur van ruimte ' . $task->room,
            'description' => 'Er is geen reactie gekomen op het rondsturen van de foto van ruimte ' . $task->room . '. Plak een sticker op de deur.',
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
            $apiUrl = config('services.storage_share_api.url') . '/photo-process/' . $task->photo_process_notification_id . '/sticker-planned';
            $apiToken = config('services.storage_share_api.token');

            try {
                Http::withToken($apiToken)->post($apiUrl, [
                    'planning_task_id' => $newTask->id,
                    'resend_in_days' => 14, // 2 weeks wait after sticker
                ]);
            } catch (\Exception $e) {
                Log::error('PhotoProcess: Failed to notify API of sticker task', ['error' => $e->getMessage()]);
            }
        }

        return view('photo-workflow.task-created', ['task' => $newTask]);
    }

    /**
     * Create a task for taking a new photo.
     */
    public function createNewPhotoTask(Task $task): \Illuminate\View\View
    {
        $newTask = Task::create([
            'location_id' => $task->location_id,
            'title' => 'Nieuwe foto maken van ruimte ' . $task->room,
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
    public function createEvacuationTask(Task $task): \Illuminate\View\View
    {
        $newTask = Task::create([
            'location_id' => $task->location_id,
            'title' => 'ONTRAUMING: Ruimte ' . $task->room,
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
