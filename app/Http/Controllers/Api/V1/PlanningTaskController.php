<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanningTaskPhotoRequest;
use App\Http\Resources\PlanningTaskPhotoResource;
use App\Http\Resources\PlanningTaskResource;
use App\Models\PlanningTask;
use App\Models\PlanningTaskPhoto;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PlanningTaskController extends Controller
{
    /**
     * Mark a planning task as completed.
     */
    public function complete(Request $request, PlanningTask $planning_task): PlanningTaskResource
    {
        $validated = $request->validate([
            'completed_notes' => 'nullable|string|max:65535',
        ]);

        $planning_task->update([
            'completed_at' => now(),
            'completed_notes' => $validated['completed_notes'] ?? null,
        ]);

        return new PlanningTaskResource($planning_task->load(['planning', 'task', 'defaultTask', 'planningTaskPhotos']));
    }

    /**
     * Mark a planning task as not completed.
     */
    public function uncomplete(PlanningTask $planning_task): PlanningTaskResource
    {
        $planning_task->update([
            'completed_at' => null,
            'completed_notes' => null,
        ]);

        return new PlanningTaskResource($planning_task->load(['planning', 'task', 'defaultTask', 'planningTaskPhotos']));
    }

    /**
     * Store a new photo for a planning task.
     */
    public function storePhoto(StorePlanningTaskPhotoRequest $request, PlanningTask $planning_task, ImageService $imageService): JsonResponse
    {
        $file = $request->file('photo');
        $originalName = $file->getClientOriginalName();
        $originalSize = $file->getSize();

        $filename = uniqid('ptp_'.$planning_task->id.'_', true).'.'.$file->getClientOriginalExtension();

        try {
            // Compress and save the image
            $path = $imageService->saveCompressedImage(
                $file,
                'planning-task-photos/'.$planning_task->id,
                $filename,
                'private'
            );

            // Get the compressed file size
            $compressedSize = strlen(Storage::disk('private')->get($path));

            $photo = $planning_task->planningTaskPhotos()->create([
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $file->getMimeType(),
                'size' => $compressedSize,
            ]);

            return (new PlanningTaskPhotoResource($photo))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fout bij het verwerken van de foto.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a photo from a planning task.
     */
    public function destroyPhoto(PlanningTask $planning_task, PlanningTaskPhoto $planning_task_photo): JsonResponse
    {
        if ($planning_task_photo->planning_task_id !== $planning_task->id) {
            return response()->json(['message' => 'Foto niet gevonden voor deze taak.'], Response::HTTP_NOT_FOUND);
        }

        Storage::disk('private')->delete($planning_task_photo->path);

        $planning_task_photo->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
