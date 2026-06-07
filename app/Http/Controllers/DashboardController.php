<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Planning;
use App\Models\PlanningTask; // Import Planning model
use App\Models\Task; // Import Task model
use App\Models\User; // Import Carbon for date handling
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $endOfWeek = Carbon::today()->endOfWeek();
        $startOfNextWeek = Carbon::today()->addWeek()->startOfWeek();
        $endOfNextWeek = Carbon::today()->addWeek()->endOfWeek();
        /** @var User $user */
        $user = $request->user();

        $planningQuery = Planning::with(['locations', 'planningTasks', 'users']);

        if (! $user->isAdmin()) {
            $planningQuery->whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            });
        }

        // Plannings for today
        $todays_plannings = (clone $planningQuery)
            ->whereDate('planned_date', $today)
            ->orderBy('id')
            ->get();

        // Plannings for the rest of the week (excluding today)
        $plannings_rest_of_week = (clone $planningQuery)
            ->whereBetween('planned_date', [$today->copy()->addDay(), $endOfWeek])
            ->orderBy('planned_date')
            ->orderBy('id')
            ->get();

        // Plannings for next week
        $plannings_next_week = (clone $planningQuery)
            ->whereBetween('planned_date', [$startOfNextWeek, $endOfNextWeek])
            ->orderBy('planned_date')
            ->orderBy('id')
            ->get();

        // Count of plannings for the entire current week
        $plannings_this_week_count = $todays_plannings->count() + $plannings_rest_of_week->count();

        if ($user->isAdmin()) {
            $backlog_open_tasks = Task::where('status', 'open')->doesntHave('planningTasks')->count();
            $review_tasks_count = Task::where('status', TaskStatus::REVIEW->value)->count();
            $review_planning_tasks_count = PlanningTask::where('status', TaskStatus::REVIEW->value)->whereNull('task_id')->count();
        } else {
            $backlog_open_tasks = 0;
            $review_tasks_count = 0;
            $review_planning_tasks_count = PlanningTask::where('status', TaskStatus::REVIEW->value)
                ->whereNull('task_id')
                ->whereHas('planning.users', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->count();
        }

        $tasks_for_review_count = $review_tasks_count + $review_planning_tasks_count;

        return view($this->viewName('dashboard'), [
            'todays_plannings' => $todays_plannings,
            'plannings_rest_of_week' => $plannings_rest_of_week,
            'plannings_next_week' => $plannings_next_week,
            'plannings_this_week_count' => $plannings_this_week_count,
            'backlog_open_tasks' => $backlog_open_tasks,
            'tasks_for_review_count' => $tasks_for_review_count,
        ]);
    }
}
