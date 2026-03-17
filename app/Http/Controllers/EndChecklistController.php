<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Models\EndChecklistItem;
use App\Models\EndChecklistItemPhoto;
use App\Models\Planning;
use App\Models\Task;
use App\Models\DefaultTask;
use App\Models\PlanningTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Services\ImageService;
use Illuminate\View\View;

class EndChecklistController extends Controller
{
    /**
     * Create end checklist items for a planning.
     */
    public function create(Request $request, Planning $planning): JsonResponse
    {
        $request->validate([
            'materials' => 'required|array',
            'materials.*' => 'exists:requirements,id',
            'end_actions' => 'required|array',
            'end_actions.*.title' => 'required|string|max:255',
            'end_actions.*.description' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $planning) {
            // Remove existing checklist items for this planning
            $planning->endChecklistItems()->delete();

            // Create material checklist items
            foreach ($request->materials as $requirementId) {
                $requirement = Requirement::find($requirementId);

                EndChecklistItem::create([
                    'planning_id' => $planning->id,
                    'type' => 'material',
                    'requirement_id' => $requirementId,
                    'title' => $requirement->name,
                    'description' => "Terugbrengen: {$requirement->name}",
                ]);
            }

            // Create end action checklist items
            foreach ($request->end_actions as $endAction) {
                EndChecklistItem::create([
                    'planning_id' => $planning->id,
                    'type' => 'end_action',
                    'title' => $endAction['title'],
                    'description' => $endAction['description'] ?? null,
                ]);
            }

        });

        return response()->json([
            'success' => true,
            'message' => 'End checklist aangemaakt',
            'items' => $planning->fresh()->endChecklistItems
        ]);
    }

    /**
     * Upload one or more photos for a checklist item.
     */
    public function uploadPhoto(Request $request, EndChecklistItem $item, ImageService $imageService): JsonResponse
    {
        $request->validate([
            // Mirror Task acceptance as much as possible; keep 10MB max per file
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max
            'photos' => 'sometimes|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        try {
            $files = [];
            if ($request->hasFile('photos')) {
                $files = $request->file('photos');
            } elseif ($request->hasFile('photo')) {
                $files = [$request->file('photo')];
            }

            if (empty($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geen foto\'s ontvangen'
                ], 422);
            }

            $saved = [];
            foreach ($files as $photo) {
                // Use ImageService for consistent compression and storage pattern like Tasks
                $filename = uniqid('eci_'.$item->id.'_', true) . '.' . $photo->getClientOriginalExtension();
                $directory = 'end-checklist-photos/'.$item->id;

                try {
                    $path = $imageService->saveCompressedImage(
                        $photo,
                        $directory,
                        $filename,
                        'public'
                    );
                } catch (\Exception $e) {
                    // If compression fails for any reason, fallback to raw store to not block the flow
                    $path = $photo->store($directory, 'public');
                }

                $saved[] = $item->photos()->create([
                    'file_path' => $path,
                    'uploaded_by' => Auth::id(),
                    'uploaded_at' => now(),
                ]);
            }

            // Also update legacy columns for backward compatibility (set to latest uploaded)
            $latest = end($saved);
            if ($latest) {
                $item->forceFill([
                    'photo_path' => $latest->file_path,
                    'uploaded_by' => Auth::id(),
                    'uploaded_at' => now(),
                ])->save();
            }

            // Reload photos with accessors
            $item->load('photos');

            return response()->json([
                'success' => true,
                'message' => 'Foto\'s succesvol geüpload',
                'photos' => $item->photos->map->only(['id', 'file_path', 'uploaded_at'])
                    ->map(function ($p) {
                        $p['photo_url'] = Storage::disk('public')->url($p['file_path']);
                        return $p;
                    })
                    ->values()
                    ->all(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fout bij uploaden van foto\'s: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all photos for a checklist item (legacy behavior used by UI when replacing all).
     */
    public function deletePhoto(EndChecklistItem $item): JsonResponse
    {
        try {
            foreach ($item->photos as $photo) {
                if ($photo->file_path && Storage::disk('public')->exists($photo->file_path)) {
                    Storage::disk('public')->delete($photo->file_path);
                }
                $photo->delete();
            }

            if ($item->photo_path && Storage::disk('public')->exists($item->photo_path)) {
                Storage::disk('public')->delete($item->photo_path);
            }

            // Clear legacy columns
            $item->update([
                'photo_path' => null,
                'uploaded_by' => null,
                'uploaded_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Alle foto\'s succesvol verwijderd'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fout bij verwijderen van foto\'s: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a single photo for a checklist item.
     */
    public function deleteSpecificPhoto(EndChecklistItem $item, EndChecklistItemPhoto $photo): JsonResponse
    {
        try {
            // Ensure the photo belongs to the item
            if ($photo->end_checklist_item_id !== $item->id) {
                return response()->json(['success' => false, 'message' => 'Foto hoort niet bij dit item'], 422);
            }

            if ($photo->file_path && Storage::disk('public')->exists($photo->file_path)) {
                Storage::disk('public')->delete($photo->file_path);
            }
            $photo->delete();

            // Update legacy column if it pointed to this photo
            if ($item->photo_path === $photo->file_path) {
                $latest = $item->photos()->latest('uploaded_at')->first();
                $item->update([
                    'photo_path' => $latest?->file_path,
                    'uploaded_by' => $latest?->uploaded_by,
                    'uploaded_at' => $latest?->uploaded_at,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Foto verwijderd'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fout bij verwijderen van foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit the complete end checklist for admin review.
     */
    public function submit(Request $request, Planning $planning): JsonResponse
    {
        // Vehicle tasks are handled via the dedicated PlanningVehicleTaskController now.

        // Check if all checklist items have at least one photo
        $itemsWithoutPhotos = $planning->endChecklistItems()
            ->doesntHave('photos')
            ->count();

        if ($itemsWithoutPhotos > 0) {
            return response()->json([
                'success' => false,
                'message' => "Er zijn nog {$itemsWithoutPhotos} items zonder foto. Upload alle foto's voordat je de checklist indient."
            ], 422);
        }

        // Update all items to pending status (in case they were rejected before)
        $planning->endChecklistItems()->update([
            'status' => 'pending',
            'admin_notes' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);

        // Update planning status
        $planning->checkAndUpdateStatus();

        return response()->json([
            'success' => true,
            'message' => 'End checklist ingediend voor beoordeling'
        ]);
    }

    /**
     * Get checklist items for a planning.
     */
    public function index(Planning $planning): JsonResponse
    {
        $items = $planning->endChecklistItems()
            ->with(['requirement', 'reviewer', 'location', 'uploader', 'photos'])
            ->orderBy('type')
            ->orderBy('title')
            ->get();

        return response()->json([
            'items' => $items,
            'has_submitted' => $planning->hasSubmittedEndChecklist(),
            'is_approved' => $planning->hasApprovedEndChecklist(),
        ]);
    }

    /**
     * Admin: Review checklist item.
     */
    public function review(Request $request, EndChecklistItem $item): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $item->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        // Update planning status based on all checklist items
        $item->planning->checkAndUpdateStatus();

        return response()->json([
            'success' => true,
            'message' => 'Checklist item beoordeeld'
        ]);
    }

    /**
     * Admin: Get plannings with pending end checklists.
     *
     */
    public function pendingReviews(): JsonResponse
    {
        $plannings = Planning::whereHas('endChecklistItems', /** @param \Illuminate\Database\Eloquent\Builder<\App\Models\EndChecklistItem> $query */ function (\Illuminate\Database\Eloquent\Builder $query): void {
            $query->where('status', 'pending')
                  ->whereHas('photos');
        })
        ->with([
            'endChecklistItems' => function ($query) {
                $query->with(['requirement', 'reviewer', 'location', 'uploader', 'photos']);
            },
            'locations',
            'users'
        ])
        ->orderBy('updated_at', 'desc')
        ->get();

        return response()->json([
            'plannings' => $plannings
        ]);
    }

    /**
     * Admin: Approve a checklist item (and all related items).
     */
    public function approveItem(Request $request, EndChecklistItem $item): JsonResponse|RedirectResponse
    {
        // Get all related items (same requirement AND title, or same end_action title)
        if ($item->type === 'material' && $item->requirement_id) {
            $related_items = EndChecklistItem::where('type', 'material')
                ->where('requirement_id', $item->requirement_id)
                ->where('title', $item->title)
                ->where('status', 'pending')
                ->get();
        } else {
            $related_items = EndChecklistItem::where('type', 'end_action')
                ->where('title', $item->title)
                ->where('status', 'pending')
                ->get();
        }

        // If no related items found, at least update the current item
        if ($related_items->isEmpty()) {
            $related_items = collect([$item]);
        }

        // Approve all related items
        foreach ($related_items as $related_item) {
            $related_item->update([
                'status' => 'approved',
                'admin_notes' => null,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);
        }

        // Update planning status for ALL affected plannings
        $affectedPlanningIds = $related_items->pluck('planning_id')->unique()->values();
        foreach ($affectedPlanningIds as $planningId) {
            if ($planning = Planning::find($planningId)) {
                $planning->checkAndUpdateStatus();
            }
        }

        $count = $related_items->count();
        $message = $count > 1 ? "Checklist item goedgekeurd (inclusief {$count} gerelateerde items)" : 'Checklist item goedgekeurd';

        // Async path
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => $message,
                'affected_count' => $count,
                'affected_item_ids' => $related_items->pluck('id')->values(),
            ]);
        }

        // If we know which planning context we came from, send the user back there
        if ($request->filled('planning_id')) {
            $planningId = (int) $request->input('planning_id');
            if ($planningId && ($planning = Planning::find($planningId))) {
                return redirect()->route('plannings.show', $planning)->with('success', $message);
            }
        }

        return redirect()->route('admin.tasks.review')->with('success', $message);
    }

    /**
     * Admin: Show reject confirmation with option to create new task.
     */
    public function showRejectForm(EndChecklistItem $item): View
    {
        return view($this->viewName('admin.end-checklist.reject'), compact('item'));
    }

    /**
     * Admin: Reject a checklist item (and all related items).
     */
    public function rejectItem(Request $request, EndChecklistItem $item): JsonResponse|RedirectResponse
    {
        $request->validate([
            'admin_notes' => 'required|string|max:1000',
            'create_new_task' => 'sometimes|boolean',
        ]);

        // Get all related items (same requirement AND title, or same end_action title)
        if ($item->type === 'material' && $item->requirement_id) {
            $related_items = EndChecklistItem::where('type', 'material')
                ->where('requirement_id', $item->requirement_id)
                ->where('title', $item->title)
                ->where('status', 'pending')
                ->get();
        } else {
            $related_items = EndChecklistItem::where('type', 'end_action')
                ->where('title', $item->title)
                ->where('status', 'pending')
                ->get();
        }

        // If no related items found, at least update the current item
        if ($related_items->isEmpty()) {
            $related_items = collect([$item]);
        }

        // Reject all related items
        foreach ($related_items as $related_item) {
            $related_item->update([
                'status' => 'rejected',
                'admin_notes' => $request->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);
        }

        // Update planning status for ALL affected plannings
        $affectedPlanningIds = $related_items->pluck('planning_id')->unique()->values();
        foreach ($affectedPlanningIds as $planningId) {
            if ($planning = Planning::find($planningId)) {
                $planning->checkAndUpdateStatus();
            }
        }

        $count = $related_items->count();
        $message = $count > 1 ? "Checklist item afgewezen (inclusief {$count} gerelateerde items)" : 'Checklist item afgewezen';

        // Check if admin wants to create a new task
        if ($request->boolean('create_new_task')) {
            // Redirect to task creation with pre-filled data
            // Determine a valid location id for the new task to avoid 404s from route-model binding.
            // Priority:
            // 1) The item-specific location
            // 2) First location on the related planning
            // 3) First location in the system
            // 4) If none are available, fall back to review page with an error
            $locationId = $item->location_id
                ?? ($item->planning != null ? $item->planning->locations()->first()?->id : null)
                ?? (\App\Models\Location::query()->first()?->id);

            if (!$locationId) {
                // No location could be determined — gracefully fall back
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'ok',
                        'message' => $message . ' Geen locatie gevonden om een nieuwe taak aan te maken.',
                        'affected_count' => $count,
                        'new_task' => null,
                    ]);
                }
                return redirect()->route('admin.tasks.review')
                    ->with('error', 'Kan geen locatie bepalen voor de nieuwe taak. De checklist is afgewezen, maar er is geen nieuwe taak aangemaakt.');
            }

            $prefill = [
                'title' => $item->title,
                'description' => ($item->description ?? '') . "\n\nGemaakt vanuit afgewezen end checklist item.\nAfwijzingsreden: " . $request->admin_notes,
                'location_id' => $locationId,
            ];

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'ok',
                    'message' => $message . ' Een nieuwe taak kan worden aangemaakt.',
                    'affected_count' => $count,
                    'new_task' => [
                        'create_url' => route('locations.tasks.create', ['location' => $locationId]),
                        'prefill' => $prefill,
                    ],
                ]);
            }

            return redirect()->route('locations.tasks.create', ['location' => $locationId])
                ->with('prefill', $prefill)
                ->with('success', $message . ' Een nieuwe taak wordt aangemaakt.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => $message,
                'affected_count' => $count,
            ]);
        }

        // If we know which planning context we came from, send the user back there
        if ($request->filled('planning_id')) {
            $planningId = (int) $request->input('planning_id');
            if ($planningId && ($planning = Planning::find($planningId))) {
                return redirect()->route('plannings.show', $planning)->with('success', $message);
            }
        }

        return redirect()->route('admin.tasks.review')->with('success', $message);
    }
}
