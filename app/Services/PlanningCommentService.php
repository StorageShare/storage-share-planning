<?php

namespace App\Services;

use App\Models\Planning;
use App\Models\PlanningComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PlanningCommentService
{
    public function __construct(
        private ImageService $imageService
    ) {}

    public function storeExtraTask(Request $request, Planning $planning, int|string $locationId): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'notes' => 'required|string',
            'photos.*' => 'nullable|image|max:10240',
            'rooms.*' => 'nullable|string',
            'photo_locations.*' => 'nullable|exists:locations,id',
        ]);

        $comment = $planning->comments()->create([
            'location_id' => $locationId === 'backlog' ? null : $locationId,
            'user_id' => $user->id,
            'comment' => $validated['notes'],
        ]);

        $this->storeCommentPhotos($request, $comment);
        $comment->load('photos');

        return response()->json([
            'comment' => array_merge($comment->toArray(), [
                'created_at' => $comment->created_at->format('H:i'),
            ]),
        ]);
    }

    public function updateComment(Request $request, PlanningComment $comment): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isAdmin() && $comment->user_id !== $user->id) {
            abort(403, 'Je hebt geen toestemming om deze opmerking te wijzigen.');
        }

        $validated = $request->validate([
            'notes' => 'required|string',
            'photos.*' => 'nullable|image|max:10240',
            'rooms.*' => 'nullable|string',
            'photo_locations.*' => 'nullable|exists:locations,id',
        ]);

        $comment->update([
            'comment' => $validated['notes'],
        ]);

        $this->storeCommentPhotos($request, $comment);
        $comment->load('photos');

        return response()->json([
            'comment' => array_merge($comment->toArray(), [
                'created_at' => $comment->created_at->format('H:i'),
            ]),
        ]);
    }

    private function storeCommentPhotos(Request $request, PlanningComment $comment): void
    {
        if (! $request->hasFile('photos')) {
            return;
        }

        $rooms = $request->input('rooms', []);
        $photoLocations = $request->input('photo_locations', []);

        foreach ($request->file('photos') as $index => $photo) {
            try {
                $filename = uniqid('pc_'.$comment->id.'_', true).'.'.$photo->getClientOriginalExtension();
                $path = $this->imageService->saveCompressedImage(
                    $photo,
                    'planning-comment-photos/'.$comment->id,
                    $filename,
                    'public'
                );
                $comment->photos()->create([
                    'file_path' => $path,
                    'room' => $rooms[$index] ?? null,
                    'location_id' => $photoLocations[$index] ?? null,
                ]);
            } catch (\Exception $e) {
                Log::error('Error compressing image: '.$e->getMessage());
                $path = $photo->store('planning-comment-photos/'.$comment->id, 'public');
                $comment->photos()->create([
                    'file_path' => $path,
                    'room' => $rooms[$index] ?? null,
                    'location_id' => $photoLocations[$index] ?? null,
                ]);
            }
        }
    }
}
