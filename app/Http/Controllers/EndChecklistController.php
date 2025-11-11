<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Models\EndChecklistItem;
use App\Models\Planning;
use App\Models\Task;
use App\Models\DefaultTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
     * Upload photo for a checklist item.
     */
    public function uploadPhoto(Request $request, EndChecklistItem $item): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 10MB max
        ]);

        try {
            // Delete old photo if exists
            if ($item->photo_path && Storage::disk('public')->exists($item->photo_path)) {
                Storage::disk('public')->delete($item->photo_path);
            }

            // Store the new photo
            $photo = $request->file('photo');
            $path = $photo->store('end-checklist-photos', 'public');

            // Update the item with the photo path and uploader info
            $item->update([
                'photo_path' => $path,
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto succesvol geupload',
                'photo_url' => asset('storage/' . $path)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fout bij uploaden van foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete photo for a checklist item.
     */
    public function deletePhoto(EndChecklistItem $item): JsonResponse
    {
        try {
            // Delete photo file if exists
            if ($item->photo_path && Storage::disk('public')->exists($item->photo_path)) {
                Storage::disk('public')->delete($item->photo_path);
            }

            // Clear photo fields
            $item->update([
                'photo_path' => null,
                'uploaded_by' => null,
                'uploaded_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto succesvol verwijderd'
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
    public function submit(Planning $planning): JsonResponse
    {
        // Check if all checklist items have photos
        $itemsWithoutPhotos = $planning->endChecklistItems()
            ->whereNull('photo_path')
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
            ->with(['requirement', 'reviewer', 'location', 'uploader'])
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
     */
    public function pendingReviews(): JsonResponse
    {
        $plannings = Planning::whereHas('endChecklistItems', function ($query) {
            $query->where('status', 'pending')
                  ->whereNotNull('photo_path');
        })
        ->with([
            'endChecklistItems' => function ($query) {
                $query->with(['requirement', 'reviewer', 'location', 'uploader']);
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
    public function approveItem(EndChecklistItem $item)
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

        // Approve all related items
        foreach ($related_items as $related_item) {
            $related_item->update([
                'status' => 'approved',
                'admin_notes' => null,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);
        }

        // Update planning status based on all checklist items
        $item->planning->checkAndUpdateStatus();

        $count = $related_items->count();
        $message = $count > 1 ? "Checklist item goedgekeurd (inclusief {$count} gerelateerde items)" : 'Checklist item goedgekeurd';

        return redirect()->route('admin.tasks.review')->with('success', $message);
    }

    /**
     * Admin: Show reject confirmation with option to create new task.
     */
    public function showRejectForm(EndChecklistItem $item)
    {
        return view('admin.end-checklist.reject', compact('item'));
    }

    /**
     * Admin: Reject a checklist item (and all related items).
     */
    public function rejectItem(Request $request, EndChecklistItem $item)
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

        // Reject all related items
        foreach ($related_items as $related_item) {
            $related_item->update([
                'status' => 'rejected',
                'admin_notes' => $request->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);
        }

        // Update planning status based on all checklist items
        $item->planning->checkAndUpdateStatus();

        $count = $related_items->count();
        $message = $count > 1 ? "Checklist item afgewezen (inclusief {$count} gerelateerde items)" : 'Checklist item afgewezen';

        // Check if admin wants to create a new task
        if ($request->boolean('create_new_task')) {
            // Redirect to task creation with pre-filled data
            $location = $item->location_id ?? 1; // Default to first location if no location set
            return redirect()->route('locations.tasks.create', ['location' => $location])
                ->with('prefill', [
                    'title' => $item->title,
                    'description' => $item->description . "\n\nGemaakt vanuit afgewezen end checklist item.\nAfwijzingsreden: " . $request->admin_notes,
                    'location_id' => $item->location_id,
                ])
                ->with('success', $message . ' Een nieuwe taak wordt aangemaakt.');
        }

        return redirect()->route('admin.tasks.review')->with('success', $message);
    }
}
