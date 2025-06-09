<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Task;
use App\Enums\TaskPriority;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB; // Added for DB::raw

class TaskBacklogController extends Controller
{
    /**
     * Display a listing of the backlog tasks.
     */
    public function index(Request $request): View
    {
        $query = Task::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->with(['location', 'planningTasks']);

        $searchTerm = $request->input('search_term', '');
        $locationId = $request->input('location_id');
        $priorityFilter = $request->input('priority');

        // Filter logic
        if ($locationId) {
            $query->where('location_id', $locationId);
        }
        if ($priorityFilter) {
            $query->where('priority', $priorityFilter);
        }

        if (!empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('tasks.title', 'LIKE', "%{$searchTerm}%") // tasks.title to be explicit due to potential join
                  ->orWhere('tasks.description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // --- Corrected Sorting Logic for TaskBacklogController ---
        $sortableColumns = ['title', 'priority', 'status', 'deadline', 'estimated_hours', 'location_name', 'created_at'];
        $sortByInput = $request->input('sort_by');
        $sortDirectionInput = $request->input('sort_direction');

        if (!$sortByInput) {
            // DEFAULT SORTING (no sort parameters in URL)
            $query->orderByRaw('(SELECT COUNT(*) FROM planning_tasks WHERE planning_tasks.task_id = tasks.id) ASC') // Unplanned first
                  ->orderByRaw('tasks.deadline IS NULL ASC, tasks.deadline ASC') // Then by Deadline ASC (NULLs last, earliest first)
                  ->orderByRaw("CASE tasks.priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC", [
                      TaskPriority::HIGH->value,
                      TaskPriority::NORMAL->value,
                      TaskPriority::LOW->value
                  ]) // Priority ASC
                  ->orderBy('tasks.created_at', 'desc'); // Created_at DESC

            $sortBy = 'deadline'; // Conceptual primary for view icon
            $sortDirection = 'asc';
        } else {
            // USER SPECIFIED SORTING
            $sortBy = $sortByInput;
            if (!in_array($sortBy, $sortableColumns)) {
                $sortBy = 'created_at'; // Fallback
                $sortDirection = 'desc';
            } else {
                $sortDirection = strtolower($sortDirectionInput) === 'desc' ? 'desc' : 'asc';
            }

            $taskTablePrefix = 'tasks.'; // Always use prefix for clarity in this controller

            if ($sortBy === 'location_name') {
                $query->join('locations', 'tasks.location_id', '=', 'locations.id')
                      ->orderBy('locations.name', $sortDirection)
                      ->select('tasks.*'); // Ensure we only select task columns after join
            } elseif ($sortBy === 'priority') {
                $query->orderByRaw(
                    "CASE {$taskTablePrefix}priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END " . $sortDirection,
                    [TaskPriority::HIGH->value, TaskPriority::NORMAL->value, TaskPriority::LOW->value]
                );
            } elseif ($sortBy === 'deadline') {
                if ($sortDirection === 'asc') {
                    $query->orderByRaw("{$taskTablePrefix}deadline IS NULL ASC, {$taskTablePrefix}deadline ASC");
                } else {
                    $query->orderByRaw("{$taskTablePrefix}deadline IS NULL ASC, {$taskTablePrefix}deadline DESC");
                }
            } else {
                $query->orderBy($taskTablePrefix . $sortBy, $sortDirection);
            }

            // Consistent tie-breakers for user-defined sorts
            if ($sortBy !== 'deadline') {
                $query->orderByRaw('tasks.deadline IS NULL ASC, tasks.deadline ASC');
            }
            if ($sortBy !== 'priority') {
                $query->orderByRaw("CASE tasks.priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC", [
                    TaskPriority::HIGH->value, TaskPriority::NORMAL->value, TaskPriority::LOW->value
                ]);
            }
            if ($sortBy !== 'created_at') {
                $query->orderBy('tasks.created_at', 'desc');
            }
            // $query->orderBy('tasks.id', 'asc'); // Ultimate tie-breaker
        }
        // --- End of Corrected Sorting Logic ---

        $tasks = $query->paginate(15)->withQueryString();
        $locations = Location::orderBy('name')->get();
        $priorities = TaskPriority::cases();

        // Consolidate active filters for the view
        $activeFilters = [
            'location_id' => $locationId,
            'priority' => $priorityFilter,
            // Add other simple string-based filters here if any in the future
        ];

        return view('backlog.index', [
            'tasks' => $tasks,
            'locations' => $locations,
            'priorities' => $priorities,
            'filters' => $activeFilters, // Pass the consolidated filters
            'sortBy' => $sortBy, // Standardized variable name
            'sortDirection' => $sortDirection, // Standardized variable name
            'searchTerm' => $searchTerm,
        ]);
    }
}
