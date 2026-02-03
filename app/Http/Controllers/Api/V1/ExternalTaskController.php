<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExternalTaskRequest;
use App\Models\ExternalTask;
use App\Models\Location;
use App\Mail\NewApiTaskReceivedMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ExternalTaskController extends Controller
{
    public function store(StoreExternalTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        $location = $this->resolveLocation($data);
        $externalDeadlineAt = $data['external_deadline_at'] ?? ($data['deadline'] ?? null);

        $task = DB::transaction(function () use ($data, $location, $externalDeadlineAt) {
            return ExternalTask::create([
                'location_id' => $location->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'feedback_information' => $data['feedback_information'] ?? null,
                'external_deadline_at' => $externalDeadlineAt,
                'estimated_time_minutes' => $data['estimated_time_minutes'] ?? null,
                'status' => TaskStatus::REVIEW, // Consistently use REVIEW status
                'priority' => $data['priority'] ?? TaskPriority::NORMAL->value,
            ]);
        });

        // Although ExternalTask is a different model, we might want to notify about it as well
        // For now, let's keep it consistent with TaskController if the goal is centralized review
        // Note: NewApiTaskReceivedMail expects App\Models\Task, so we'd need a separate mail or adjust the model type hint if ExternalTask is still used.
        // Given the feedback, it's likely they want to move towards the main Task model.

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
