<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View; // Added for DB::raw

class TaskBacklogController extends Controller
{
    /**
     * Display a listing of the backlog tasks.
     */
    public function index(Request $request): View
    {
        $query = Task::query();

        $showCompleted = $request->boolean('show_completed');

        // Read status filter with legacy support for only_concept
        $statusParam = $request->input('status');
        if (!$statusParam && $request->boolean('only_concept')) {
            $statusParam = TaskStatus::CONCEPT->value; // legacy mapping
        }
        $validStatuses = array_column(TaskStatus::cases(), 'value');
        $statusFilter = in_array($statusParam, $validStatuses, true) ? $statusParam : null;

        $user = Auth::user();
        if ($statusFilter) {
            // Explicit status filter takes precedence for all roles
            $query->where('status', $statusFilter);
        } else {
            if ($user && $user->role === Role::CUSTOMER_SERVICE) {
                // Customer service users should only see concept tasks in the backlog by default
                $query->where('status', TaskStatus::CONCEPT->value);
            } else {
                if (! $showCompleted) {
                    $query->whereIn('status', ['concept', 'open', 'in_progress', 'in_review']);
                }
            }
        }

        $searchTerm = $request->input('search_term', '');
        $locationId = $request->input('location_id');
        $priorityFilter = $request->input('priority');
        $includeRecurring = $request->boolean('include_recurring', false);
        $onlyConcept = $request->boolean('only_concept', false);

        // Filter logic
        if ($locationId) {
            $query->where('location_id', $locationId);
        }
        if ($priorityFilter) {
            $query->where('priority', $priorityFilter);
        }
        // Exclude recurring tasks by default (can include with include_recurring=true)
        if (! $includeRecurring) {
            $query->where('is_recurring', false);
        }

        if (! empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('tasks.title', 'LIKE', "%{$searchTerm}%") // tasks.title to be explicit due to potential join
                    ->orWhere('tasks.description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // --- Corrected Sorting Logic for TaskBacklogController ---
        $sortableColumns = ['title', 'priority', 'status', 'deadline', 'estimated_hours', 'location_name', 'created_at'];
        $sortByInput = $request->input('sort_by');
        $sortDirectionInput = $request->input('sort_direction');

        if (! $sortByInput) {
            // DEFAULT SORTING (no sort parameters in URL)
            $query->orderByRaw('tasks.deadline IS NULL ASC, tasks.deadline ASC') // Then by Deadline ASC (NULLs last, earliest first)
                ->orderByRaw('CASE tasks.priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC', [
                    TaskPriority::HIGH->value,
                    TaskPriority::NORMAL->value,
                    TaskPriority::LOW->value,
                ]); // Priority ASC

            $sortBy = 'deadline'; // Conceptual primary for view icon
            $sortDirection = 'asc';
        } else {
            // USER SPECIFIED SORTING
            $sortBy = $sortByInput;
            if (! in_array($sortBy, $sortableColumns)) {
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
                    "CASE {$taskTablePrefix}priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ".$sortDirection,
                    [TaskPriority::HIGH->value, TaskPriority::NORMAL->value, TaskPriority::LOW->value]
                );
            } elseif ($sortBy === 'deadline') {
                if ($sortDirection === 'asc') {
                    $query->orderByRaw("{$taskTablePrefix}deadline IS NULL ASC, {$taskTablePrefix}deadline ASC");
                } else {
                    $query->orderByRaw("{$taskTablePrefix}deadline IS NULL ASC, {$taskTablePrefix}deadline DESC");
                }
            } else {
                $query->orderBy($taskTablePrefix.$sortBy, $sortDirection);
            }

            // Consistent tie-breakers for user-defined sorts
            if ($sortBy !== 'deadline') {
                $query->orderByRaw('tasks.deadline IS NULL ASC, tasks.deadline ASC');
            }
            if ($sortBy !== 'priority') {
                $query->orderByRaw('CASE tasks.priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC', [
                    TaskPriority::HIGH->value, TaskPriority::NORMAL->value, TaskPriority::LOW->value,
                ]);
            }
            if ($sortBy !== 'created_at') {
                $query->orderBy('tasks.created_at', 'desc');
            }
            $query->orderBy('tasks.id', 'desc'); // Ultimate tie-breaker for stability
        }
        // --- End of Corrected Sorting Logic ---

        // Eager load relationships before pagination to avoid calling load() on paginator
        $query->with(['location', 'planningTasks.planning']);

        $perPage = $this->resolvePerPage($request, $query, 30);
        $tasks = $query->paginate($perPage)->withQueryString();

        $locations = Location::orderBy('name')->get();
        $priorities = TaskPriority::cases();

        // Consolidate active filters for the view
        $activeFilters = [
            'location_id' => $locationId,
            'priority' => $priorityFilter,
            'status' => $statusFilter,
            'show_completed' => $showCompleted,
            'include_recurring' => $includeRecurring,
            'only_concept' => $onlyConcept, // legacy presence indicator
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

    /**
     * Bulk delete selected backlog tasks.
     */
    public function bulkDestroy(Request $request)
    {
        $user = Auth::user();
        abort_unless($user && in_array($user->role, [Role::ADMIN, Role::FACILITIES_COORDINATOR], true), 403);

        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
        ], [
            'task_ids.required' => 'Selecteer minstens één taak om te verwijderen.',
            'task_ids.array' => 'Ongeldige selectie opgegeven.',
            'task_ids.min' => 'Selecteer minstens één taak om te verwijderen.',
        ]);

        $ids = $validated['task_ids'];

        DB::transaction(function () use ($ids) {
            Task::whereIn('id', $ids)->delete();
        });

        $count = count($ids);

        // Redirect back to the previous list (preserves filters and pagination via referrer)
        return back()->with('success', $count.' taken succesvol verwijderd.');
    }
}
