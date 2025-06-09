<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DefaultTask;
use App\Http\Requests\StoreDefaultTaskRequest;
use App\Http\Requests\UpdateDefaultTaskRequest;
use App\Http\Resources\DefaultTaskResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DefaultTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return DefaultTaskResource::collection(DefaultTask::latest()->paginate(10));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDefaultTaskRequest $request): JsonResponse
    {
        $defaultTask = DefaultTask::create($request->validated());
        return (new DefaultTaskResource($defaultTask))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(DefaultTask $defaultTask): DefaultTaskResource
    {
        return new DefaultTaskResource($defaultTask);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDefaultTaskRequest $request, DefaultTask $defaultTask): DefaultTaskResource
    {
        $defaultTask->update($request->validated());
        return new DefaultTaskResource($defaultTask);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DefaultTask $defaultTask): JsonResponse
    {
        $defaultTask->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
