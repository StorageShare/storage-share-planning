<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OfflineSyncQueue;
use App\Models\PlanningTask;
use App\Models\PlanningTaskCompletion;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflineSyncController extends Controller
{
    public function syncPlanningTasks(Request $request): JsonResponse
    {
        $request->validate([
            'completions' => 'required|array',
            'completions.*.planning_task_id' => 'required|exists:planning_tasks,id',
            'completions.*.sync_hash' => 'required|string',
            'completions.*.comment' => 'required|string',
            'completions.*.is_fully_completed' => 'required|boolean',
            'completions.*.completed_offline_at' => 'required|date',
            'completions.*.task_duration_seconds' => 'sometimes|integer|min:0',
        ]);

        $results = [];
        /** @var \App\Models\User $user */
        $user = Auth::user();

        foreach ($request->input('completions') as $completionData) {
            try {
                $results[] = $this->syncSingleCompletion($completionData, $user);
            } catch (\Exception $e) {
                Log::error('Sync error for completion: '.$e->getMessage(), $completionData);
                $results[] = [
                    'sync_hash' => $completionData['sync_hash'],
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    private function syncSingleCompletion(array $data, User $user): array
    {
        // Controleer of deze completion al eerder is gesynchroniseerd
        if (PlanningTaskCompletion::where('sync_hash', $data['sync_hash'])->exists()) {
            return [
                'sync_hash' => $data['sync_hash'],
                'status' => 'already_synced',
            ];
        }

        return DB::transaction(function () use ($data, $user) {
            $planningTask = PlanningTask::findOrFail($data['planning_task_id']);

            $completion = $planningTask->completions()->create([
                'user_id' => $user->id,
                'comment' => $data['comment'],
                'is_fully_completed' => $data['is_fully_completed'],
                'sync_hash' => $data['sync_hash'],
                'created_at' => $data['completed_offline_at'],
                'task_duration_seconds' => $data['task_duration_seconds'] ?? 0,
            ]);

            // Update planning task status
            $planningTask->update([
                'completed_at' => $data['completed_offline_at'],
                'completed_notes' => $data['comment'],
                'status' => $user->isAdmin() ? 'completed' : 'review',
            ]);

            // Update original task status if this is a backlog task
            if ($planningTask->task) {
                $planningTask->task->update([
                    'status' => $user->isAdmin() ? 'completed' : 'review',
                ]);
            }

            return [
                'sync_hash' => $data['sync_hash'],
                'status' => 'synced',
                'completion_id' => $completion->id,
            ];
        });
    }

    public function syncPhotos(Request $request, ImageService $imageService): JsonResponse
    {
        $request->validate([
            'photos' => 'required|array',
            'photos.*.completion_sync_hash' => 'required|string',
            'photos.*.sync_hash' => 'required|string',
            'photos.*.file_data' => 'required|string', // Base64 encoded
            'photos.*.filename' => 'required|string',
            'photos.*.taken_at' => 'required|date',
        ]);

        $results = [];

        foreach ($request->input('photos') as $photoData) {
            try {
                $results[] = $this->syncSinglePhoto($photoData, $imageService);
            } catch (\Exception $e) {
                Log::error('Photo sync error: '.$e->getMessage(), $photoData);
                $results[] = [
                    'sync_hash' => $photoData['sync_hash'],
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    private function syncSinglePhoto(array $data, ImageService $imageService): array
    {
        // Vind de completion
        $completion = PlanningTaskCompletion::where('sync_hash', $data['completion_sync_hash'])->first();

        if (! $completion) {
            throw new \Exception('Completion not found for sync_hash: '.$data['completion_sync_hash']);
        }

        // Controleer of foto al bestaat
        if ($completion->photos()->where('sync_hash', $data['sync_hash'])->exists()) {
            return [
                'sync_hash' => $data['sync_hash'],
                'status' => 'already_synced',
            ];
        }

        // Decode en sla foto op
        $fileData = base64_decode($data['file_data']);
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        file_put_contents($tempPath, $fileData);

        $extension = pathinfo($data['filename'], PATHINFO_EXTENSION);
        $filename = uniqid('ptc_'.$completion->id.'_', true).'.'.$extension;

        $uploadedFile = new UploadedFile(
            $tempPath,
            $data['filename'],
            mime_content_type($tempPath),
            null,
            true
        );

        $path = $imageService->saveCompressedImage(
            $uploadedFile,
            'planning-task-completion-photos/'.$completion->id,
            $filename,
            'public'
        );

        $photo = $completion->photos()->create([
            'file_path' => $path,
            'sync_hash' => $data['sync_hash'],
            'created_at' => $data['taken_at'],
        ]);

        fclose($tempFile);

        return [
            'sync_hash' => $data['sync_hash'],
            'status' => 'synced',
            'photo_id' => $photo->id,
        ];
    }

    public function getSyncStatus(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $pendingItems = OfflineSyncQueue::forUser($user->id)
            ->pending()
            ->byPriority()
            ->get();

        $stats = [
            'pending_count' => $pendingItems->count(),
            'last_sync' => $pendingItems->max('updated_at'),
            'next_sync_item' => $pendingItems->first(),
        ];

        return response()->json($stats);
    }
}
