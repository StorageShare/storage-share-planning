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
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Store a newly created task in storage via API.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        // Resolve the location by internal id, or by external id when only that is provided.
        $location = $this->resolveLocation($validatedData);
        $validatedData['location_id'] = $location->id;
        unset($validatedData['location_external_id']);

        // Set creator if authenticated
        $validatedData['created_by'] = Auth::id();

        // Default priority if not provided (handled in StoreTaskRequest)
        if (!isset($validatedData['priority'])) {
            $validatedData['priority'] = TaskPriority::NORMAL->value;
        }

        // Default status to REVIEW for API created tasks so they can be reviewed by Jaap
        $status = TaskStatus::REVIEW;

        // If it's CUSTOMER_SERVICE, it can also stay CONCEPT or also go to REVIEW
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
            'task_id' => $task->id,
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

    /**
     * Resolve the target location by internal id or by external id.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    private function resolveLocation(array $data): Location
    {
        if (! empty($data['location_id'])) {
            return Location::findOrFail($data['location_id']);
        }

        $externalId = $data['location_external_id'] ?? null;
        if ($externalId === null) {
            throw ValidationException::withMessages([
                'location_id' => 'Location is required.',
            ]);
        }

        $location = Location::query()
            ->where('external_id', $externalId)
            ->orWhere('sync_external_id', $externalId)
            ->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'location_external_id' => 'No location found for the provided external id.',
            ]);
        }

        return $location;
    }
}
