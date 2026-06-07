<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExternalTaskRequest;
use App\Models\ExternalTask;
use App\Services\LocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ExternalTaskController extends Controller
{
    public function __construct(private readonly LocationResolver $locationResolver) {}

    public function store(StoreExternalTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        $location = $this->locationResolver->resolve($data);
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

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
        ], 201);
    }
}
