<?php

namespace App\Services;

use App\Models\PlanningTask;
use App\Models\PlanningTaskCompletion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanningTaskCompletionPhotoService
{
    public function __construct(
        private ImageService $imageService
    ) {}

    public function copyFromPreviousCompletion(PlanningTask $planningTask, PlanningTaskCompletion $completion): void
    {
        $previousCompletion = $planningTask->completions()
            ->where('id', '!=', $completion->id)
            ->where('review_outcome', '!=', 'reopened')
            ->latest()
            ->first();

        if (! $previousCompletion) {
            return;
        }

        foreach ($previousCompletion->photos as $oldPhoto) {
            $completion->photos()->create([
                'file_path' => $oldPhoto->file_path,
            ]);
        }
    }

    public function storeUploadedPhotos(Request $request, PlanningTaskCompletion $completion): void
    {
        if (! $request->hasFile('photos')) {
            return;
        }

        foreach ($request->file('photos') as $photo) {
            try {
                $filename = uniqid('ptc_'.$completion->id.'_', true).'.'.$photo->getClientOriginalExtension();
                $path = $this->imageService->saveCompressedImage(
                    $photo,
                    'planning-task-completion-photos/'.$completion->id,
                    $filename,
                    'public'
                );
                $completion->photos()->create(['file_path' => $path]);
            } catch (\Exception $e) {
                Log::error('Error compressing image: '.$e->getMessage());
                $path = $photo->store('planning-task-completion-photos/'.$completion->id, 'public');
                $completion->photos()->create(['file_path' => $path]);
            }
        }
    }
}
