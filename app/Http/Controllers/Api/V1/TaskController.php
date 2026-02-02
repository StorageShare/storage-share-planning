<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Models\Location;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * Store a newly created task in storage via API.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        // Ensure location exists (already handled by StoreTaskRequest validation)
        $location = Location::findOrFail($validatedData['location_id']);

        // Set creator if authenticated
        $validatedData['created_by'] = Auth::id();

        // Default priority if not provided (handled in StoreTaskRequest)
        if (!isset($validatedData['priority'])) {
            $validatedData['priority'] = TaskPriority::NORMAL->value;
        }

        // Default status to OPEN for API created tasks, unless they are CUSTOMER_SERVICE then CONCEPT
        $status = TaskStatus::OPEN;
        if (Auth::check() && Auth::user()->role === \App\Enums\Role::CUSTOMER_SERVICE) {
            $status = TaskStatus::CONCEPT;
        }
        $validatedData['status'] = $status;

        $task = $location->tasks()->create($validatedData);

        // Sync requirements if provided
        if (!empty($validatedData['requirements'])) {
            $task->requirements()->sync($validatedData['requirements']);
        }

        return response()->json([
            'success' => true,
            'message' => "Taak \"{$task->title}\" succesvol aangemaakt.",
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => [
                    'value' => $task->priority->value,
                    'label' => $task->priority->label(),
                ],
                'status' => $task->status,
                'deadline' => $task->deadline,
                'estimated_time_minutes' => $task->estimated_time_minutes ?? 0,
                'location_id' => $task->location_id,
            ]
        ], 201);
    }
}
