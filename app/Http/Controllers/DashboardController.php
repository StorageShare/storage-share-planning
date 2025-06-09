<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Planning; // Import Planning model
use App\Models\Task; // Import Task model
use Carbon\Carbon; // Import Carbon for date handling

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): View
    {
        $today = Carbon::today();
        $endOfWeek = Carbon::today()->endOfWeek();
        $startOfNextWeek = Carbon::today()->addWeek()->startOfWeek();
        $endOfNextWeek = Carbon::today()->addWeek()->endOfWeek();

        // Plannings for today
        $todays_plannings = Planning::with(['locations', 'planningTasks'])
            ->whereDate('planned_date', $today)
            ->orderBy('id')
            ->get();

        // Plannings for the rest of the week (excluding today)
        $plannings_rest_of_week = Planning::with(['locations', 'planningTasks'])
            ->whereBetween('planned_date', [$today->copy()->addDay(), $endOfWeek])
            ->orderBy('planned_date')
            ->orderBy('id')
            ->get();
            
        // Plannings for next week
        $plannings_next_week = Planning::with(['locations', 'planningTasks'])
            ->whereBetween('planned_date', [$startOfNextWeek, $endOfNextWeek])
            ->orderBy('planned_date')
            ->orderBy('id')
            ->get();

        // Count of plannings for the entire current week
        $plannings_this_week_count = $todays_plannings->count() + $plannings_rest_of_week->count();
        
        $backlog_open_tasks = Task::where('status', 'open')->doesntHave('planningTasks')->count();

        return view('dashboard', [
            'todays_plannings' => $todays_plannings,
            'plannings_rest_of_week' => $plannings_rest_of_week,
            'plannings_next_week' => $plannings_next_week,
            'plannings_this_week_count' => $plannings_this_week_count,
            'backlog_open_tasks' => $backlog_open_tasks,
        ]);
    }
}
