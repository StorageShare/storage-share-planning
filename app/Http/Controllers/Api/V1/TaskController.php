<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    /**
     * Display a listing of the tasks for a specific location.
     * Route: GET /api/v1/locations/{location}/tasks
     */
    public function index(Location $location): AnonymousResourceCollection
    {
        $tasks = $location->tasks()->with('taskPhotos')->latest()->paginate(10);

        return TaskResource::collection($tasks);
    }

    /**
     * Store a newly created task for a specific location.
     * Route: POST /api/v1/locations/{location}/tasks
     */
    public function store(StoreTaskRequest $request, Location $location): JsonResponse
    {
        // StoreTaskRequest zorgt voor validatie en het mergen van location_id van de route.
        $data = $request->validated();
        if (auth()->check() && auth()->user()->role === Role::CUSTOMER_SERVICE) {
            $data['status'] = TaskStatus::CONCEPT;
        }
        $task = $location->tasks()->create($data);
        $task->load('taskPhotos'); // Laad relaties voor de resource response

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified task.
     * Route: GET /api/v1/tasks/{task} (door shallow nesting)
     */
    public function show(Task $task): TaskResource
    {
        $task->load(['location', 'taskPhotos']); // Eager load relaties

        return new TaskResource($task);
    }

    /**
     * Update the specified task in storage.
     * Route: PUT/PATCH /api/v1/tasks/{task}
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());
        $task->load(['location', 'taskPhotos']);

        return new TaskResource($task);
    }

    /**
     * Remove the specified task from storage.
     * Route: DELETE /api/v1/tasks/{task}
     */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
