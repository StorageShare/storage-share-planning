<?php

namespace App\Http\Controllers;

use App\Models\Planning;
use App\Models\PlanningTask;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator; // For manual validation if needed

class PlanningTaskController extends Controller
{
    /**
     * Mark a planning task as completed.
     */
    public function complete(Request $request, Planning $planning, PlanningTask $planning_task): RedirectResponse
    {
        if ($planning_task->planning_id !== $planning->id) {
            abort(404); // Or 403 if it's an authorization issue
        }

        // Validate notes if provided
        $request->validate([
            'completed_notes' => 'nullable|string|max:65535',
        ]);

        $planning_task->update([
            'completed_at' => now(),
            'completed_notes' => $request->input('completed_notes'),
        ]);

        return redirect()->route('plannings.show', $planning)->with('success', "Taak '{$planning_task->title}' als voltooid gemarkeerd.");
    }

    /**
     * Mark a planning task as not completed.
     */
    public function uncomplete(Planning $planning, PlanningTask $planning_task): RedirectResponse
    {
        if ($planning_task->planning_id !== $planning->id) {
            abort(404);
        }

        $planning_task->update([
            'completed_at' => null,
            'completed_notes' => null, // Also clear notes when uncompleting
        ]);

        return redirect()->route('plannings.show', $planning)->with('success', "Taak '{$planning_task->title}' als openstaand gemarkeerd.");
    }

    // Toekomstige methode voor het web toevoegen van foto's (indien nodig)
    // public function storePhoto(Request $request, Planning $planning, PlanningTask $planning_task): RedirectResponse
    // {
    //     if ($planning_task->planning_id !== $planning->id) {
    //         abort(404);
    //     }

    //     $request->validate([
    //         'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048', // Max 2MB
    //     ]);

    //     if ($request->hasFile('photo')) {
    //         $file = $request->file('photo');
    //         $originalName = $file->getClientOriginalName();
    //         $path = $file->store('planning-task-photos/' . $planning_task->id, 'public');

    //         $planning_task->planningTaskPhotos()->create([
    //             'path' => $path,
    //             'original_name' => $originalName,
    //             'mime_type' => $file->getMimeType(),
    //             'size' => $file->getSize(),
    //         ]);

    //         return redirect()->route('plannings.show', $planning)->with('success', 'Foto succesvol toegevoegd aan taak.');
    //     }

    //     return redirect()->route('plannings.show', $planning)->with('error', 'Kon foto niet uploaden.');
    // }
}
