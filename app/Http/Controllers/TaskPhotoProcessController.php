<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mail\InternalCheckRequestMail;
use App\Mail\RoomPhotoDistributedMail;
use App\Models\Task;
use Illuminate\Http\Request;
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

        // Placeholder for API connection with backend to send to all customers
        // TODO: Implement API connection with backend to send photo to all customers.
        // The backend API is not yet available.

        // Internal mail for tracking (optional, but good for demo)
        // In a real scenario, this might be sent to customers.
        // Mail::to('huur@storage-share.nl')->send(new RoomPhotoDistributedMail($task));

        return back()->with('success', 'Foto is succesvol gemarkeerd voor rondsturen. Het proces is gestart.');
    }

    /**
     * Create a task for sticking a sticker.
     */
    public function createStickerTask(Task $task)
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
