<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mail\InternalCheckRequestMail;
use App\Mail\RoomPhotoDistributedMail;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TaskPhotoProcessController extends Controller
{
    /**
     * Start the photo distribution process.
     */
    public function distribute(Request $request, Task $task)
    {
        $request->validate([
            'room' => 'required|string',
        ]);

        $task->update([
            'room' => $request->room,
            'photo_process_step' => 'PHOTO_DISTRIBUTED',
            'photo_process_at' => now(),
        ]);

        $photo = $task->taskPhotos()->latest('uploaded_at')->first();

        if (!$photo) {
            return back()->with('error', 'Geen foto gevonden voor deze taak om rond te sturen.');
        }

        // API connection with backend to send to all customers
        $apiUrl = config('services.storage_share_api.url') . '/photo-process/distribute';
        $apiToken = config('services.storage_share_api.token');

        try {
            $response = Http::withToken($apiToken)
                ->post($apiUrl, [
                    'stalling_location_id' => $task->location->external_id,
                    'photo_url' => $photo->url,
                    'room_identifier' => $task->room,
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
     * Create a task for sticking a sticker.
     */
    public function createStickerTask(Request $request, Task $task)
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
    public function createNewPhotoTask(Task $task)
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
    public function createEvacuationTask(Task $task)
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
