<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\DefaultVehicleTask;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\VehicleTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlanningVehicleTaskController extends Controller
{
    /**
     * Store vehicle related tasks for a planning as their own step.
     * - If the planning has a vehicle assigned, creates VehicleTask entries.
     * - Otherwise, persists as PlanningTask rows flagged with is_vehicle_task = true.
     */
    public function store(Request $request, Planning $planning): JsonResponse
    {
        $data = $request->validate([
            'vehicle_tasks' => 'required|array|min:1',
            'vehicle_tasks.*.default_id' => 'nullable|exists:default_vehicle_tasks,id',
            'vehicle_tasks.*.title' => 'required_without:vehicle_tasks.*.default_id|string|max:255',
            'vehicle_tasks.*.description' => 'nullable|string',
            'vehicle_tasks.*.estimated_time_minutes' => 'nullable|integer|min:0',
        ]);

        $vehicleTasks = $data['vehicle_tasks'];

        // Preload defaults to minimize queries
        $defaultsById = collect();
        $defaultIds = collect($vehicleTasks)->pluck('default_id')->filter()->unique();
        if ($defaultIds->isNotEmpty()) {
            $defaultsById = DefaultVehicleTask::whereIn('id', $defaultIds)->get()->keyBy('id');
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        foreach ($vehicleTasks as $vtInput) {
            $title = $vtInput['title'] ?? null;
            $description = $vtInput['description'] ?? null;
            $estimated = $vtInput['estimated_time_minutes'] ?? null;

            if (!empty($vtInput['default_id'])) {
                $def = $defaultsById->get($vtInput['default_id']);
                if ($def) {
                    $title = $def->title;
                    $description = $description ?? $def->description;
                    $estimated = $estimated ?? $def->estimated_time_minutes;
                }
            }

            if (!$title) {
                // Skip invalid entries silently (already validated, so this is just extra safety)
                continue;
            }

            if ($planning->vehicle_id) {
                VehicleTask::create([
                    'vehicle_id' => $planning->vehicle_id,
                    'title' => $title,
                    'description' => $description,
                    'estimated_time_minutes' => $estimated,
                    'status' => TaskStatus::OPEN,
                    'created_by' => $user?->id,
                ]);
            } else {
                PlanningTask::create([
                    'planning_id' => $planning->id,
                    'title' => $title,
                    'description' => $description,
                    'estimated_time_minutes' => $estimated,
                    'status' => TaskStatus::OPEN,
                    'is_vehicle_task' => true,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Voertuig taken opgeslagen',
        ]);
    }
}
