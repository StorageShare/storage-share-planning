<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExternalTaskRequest;
use App\Models\Location;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalTaskController extends Controller
{
    public function store(StoreExternalTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        $location = $this->resolveLocation($data);

        $task = DB::transaction(function () use ($data, $location) {
            $task = Task::create([
                'location_id' => $location->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'feedback_information' => $data['feedback_information'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'estimated_time_minutes' => $data['estimated_time_minutes'] ?? null,
                'status' => TaskStatus::OPEN,
                'priority' => $data['priority'] ?? TaskPriority::NORMAL->value,
                'created_by' => null,
            ]);

            return $task;
        });

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
        ], 201);
    }

    private function resolveLocation(array $data): Location
    {
        if (!empty($data['location_id'])) {
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

        if (!$location) {
            throw ValidationException::withMessages([
                'location_external_id' => 'No location found for the provided external id.',
            ]);
        }

        return $location;
    }

}
