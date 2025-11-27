<?php

namespace App\Http\Controllers;

use App\Models\DefaultVehicleTask;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class DefaultVehicleTaskController extends Controller
{
    /**
     * List all default vehicle tasks (HTML view placeholder for admins).
     */
    public function index(Request $request): View
    {
        $defaults = DefaultVehicleTask::orderBy('created_at', 'desc')->paginate(20);
        return view('default-vehicle-tasks.index', compact('defaults'));
    }

    /**
     * Return active default vehicle tasks as JSON for quick selection in UI.
     */
    public function active(Request $request): JsonResponse
    {
        $defaults = DefaultVehicleTask::where('active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'estimated_time_minutes']);

        return response()->json([
            'data' => $defaults,
        ]);
    }
}
