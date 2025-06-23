<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskPhotoResource;
use App\Models\Task;
use App\Models\TaskPhoto;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TaskPhotoController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Task $task, ImageService $imageService): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:20480', // Max 20MB - will be compressed to 2MB
        ]);

        $file = $request->file('photo');
        $originalName = $file->getClientOriginalName();
        $filename = uniqid('tp_'.$task->id.'_', true).'.'.$file->getClientOriginalExtension();

        try {
            // Compress and save the image
            $path = $imageService->saveCompressedImage(
                $file,
                'task-photos/'.$task->id,
                $filename,
                'public'
            );

            $photo = $task->taskPhotos()->create([
                'file_path' => $path,
                'uploaded_at' => now(),
            ]);

            return (new TaskPhotoResource($photo))
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
     * Remove the specified resource from storage.
     */
    public function destroy(TaskPhoto $taskPhoto): JsonResponse
    {
        Storage::disk('public')->delete($taskPhoto->file_path);
        $taskPhoto->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
