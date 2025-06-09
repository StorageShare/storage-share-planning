<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Task;
use App\Enums\TaskPriority;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource for a specific location.
     * De 'index' route is vaak /locations/{location}/tasks
     */
    public function index(Request $request, Location $location): View
    {
        $query = $location->tasks();

        $searchTerm = $request->input('search_term', '');
        $activeFilter = $request->input('filter');
        $plannedFilter = $request->input('planned_filter');

        // Valid sortable columns for tasks
        $sortableColumns = ['title', 'priority', 'status', 'deadline', 'estimated_hours', 'created_at'];

        $sortByInput = $request->input('sort_by');
        $sortDirectionInput = $request->input('sort_direction');

        if (!$sortByInput) {
            // DEFAULT SORTING (no sort parameters in URL)
            $query->orderByRaw('deadline IS NULL ASC, deadline ASC') // Tasks with deadlines first (earliest), then tasks without deadlines
                  ->orderByRaw("CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC", [
                      TaskPriority::HIGH->value,
                      TaskPriority::NORMAL->value,
                      TaskPriority::LOW->value
                  ]) // Priority ASC (High > Normal > Low)
                  ->orderBy('created_at', 'desc'); // Created_at DESC (Newest first)

            // Set $sortBy and $sortDirection for the view to reflect the conceptual default primary sort
            $sortBy = 'deadline'; // Default view state reflects deadline as primary of the set
            $sortDirection = 'asc'; // Default direction for deadline is now ASC
        } else {
            // USER SPECIFIED SORTING
            $sortBy = $sortByInput;
            if (!in_array($sortBy, $sortableColumns)) {
                $sortBy = 'created_at'; // Fallback if invalid column
                $sortDirection = 'desc';
            } else {
                $sortDirection = strtolower($sortDirectionInput) === 'desc' ? 'desc' : 'asc';
            }

            // Apply primary user-defined sort
            if ($sortBy === 'priority') {
                $query->orderByRaw(
                    "CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END " . $sortDirection,
                    [TaskPriority::HIGH->value, TaskPriority::NORMAL->value, TaskPriority::LOW->value]
                );
            } elseif ($sortBy === 'deadline') {
                if ($sortDirection === 'asc') {
                    $query->orderByRaw('ISNULL(deadline) ASC, deadline ASC'); // NULLs last
                } else { // desc
                    $query->orderByRaw('ISNULL(deadline) ASC, deadline DESC'); // NULLs last, then by deadline DESC
                }
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Consistent tie-breakers for user-defined sorts
            if ($sortBy !== 'created_at' && $sortBy !== 'deadline') { // Avoid re-adding if primary or part of deadline's complex sort
                $query->orderByRaw('ISNULL(deadline) ASC, deadline ASC');
            }
            if ($sortBy !== 'priority') {
                 $query->orderByRaw("CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC", [
                      TaskPriority::HIGH->value, TaskPriority::NORMAL->value, TaskPriority::LOW->value
                  ]);
            }
            if ($sortBy !== 'created_at') {
                $query->orderBy('created_at', 'desc');
            }
            // Add an ultimate tie-breaker for absolute consistency if needed, e.g., by ID
            // $query->orderBy('id', 'asc');
        }

        // Search functionality
        if (!empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter functionality
        if ($activeFilter) {
            match ($activeFilter) {
                'open' => $query->where('status', '!=', 'completed'),
                'completed' => $query->where('status', '=', 'completed'),
                'priority_high' => $query->where('priority', TaskPriority::HIGH),
                'priority_normal' => $query->where('priority', TaskPriority::NORMAL),
                'priority_low' => $query->where('priority', TaskPriority::LOW),
                default => null,
            };
        }

        // Filter by planned status
        if ($plannedFilter) {
            match ($plannedFilter) {
                'planned' => $query->whereHas('planningTasks'),
                'unplanned' => $query->whereDoesntHave('planningTasks'),
                default => null, // 'all' or any other value will not apply this specific filter
            };
        }

        $tasks = $query->paginate(15)->withQueryString();

        return view('tasks.index', compact(
            'location',
            'tasks',
            'sortBy',
            'sortDirection',
            'searchTerm',
            'activeFilter',
            'plannedFilter'
        ));
    }

    /**
     * Show a page to select a location before creating a new task.
     */
    public function selectLocationForTask(Request $request): View
    {
        $searchTerm = $request->input('search_term', '');

        $locationsQuery = Location::query();

        if (!empty($searchTerm)) {
            $locationsQuery->whereRaw('LOWER(name) LIKE ?', [strtolower("%{$searchTerm}%")]);
        }

        $locations = $locationsQuery->orderBy('name')->get();
        
        return view('tasks.select-location', compact('locations', 'searchTerm'));
    }

    /**
     * Show the form for creating a new resource for a specific location.
     * De 'create' route is vaak /locations/{location}/tasks/create
     */
    public function create(Location $location): View
    {
        return view('tasks.create', compact('location'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request, Location $location): RedirectResponse
    {
        // De StoreTaskRequest zou al gevalideerd moeten hebben dat location_id overeenkomt
        // of de location_id uit de route parameter moeten gebruiken.
        $validatedData = $request->validated();
        $validatedData['created_by'] = auth()->id();

        $new_task = $location->tasks()->create($validatedData); // Assign to variable to use in message if needed

        // Redirect to the main backlog page with a success message.
        return redirect()->route('backlog.index')->with('success', "Taak \"{$new_task->title}\" succesvol aangemaakt en toegevoegd aan de backlog.");
    }

    /**
     * Display the specified resource.
     * Door 'shallow nesting' is de route vaak /tasks/{task}
     */
    public function show(Task $task): View
    {
        // Eager load de locatie voor context, indien nodig
        $task->load('location');
        return view('tasks.show', compact('task'));
    }

    /**
     * Show the form for editing the specified resource.
     * Door 'shallow nesting' is de route vaak /tasks/{task}/edit
     */
    public function edit(Task $task): View
    {
        $task->load('location'); // Nodig voor context in de view, bv. broodkruimels
        return view('tasks.edit', compact('task'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());
        // Redirect naar de taak show pagina, of de taken index van de locatie.
        return redirect()->route('tasks.show', $task)->with('success', 'Taak succesvol bijgewerkt.');
        // Alternatief: return redirect()->route('locations.tasks.index', $task->location_id)->with('success', 'Taak succesvol bijgewerkt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $location = $task->location; // Bewaar locatie voor redirect voordat taak verwijderd wordt
        $task->delete();
        return redirect()->route('locations.tasks.index', $location)->with('success', 'Taak succesvol verwijderd.');
        // Alternatief: return redirect()->route('locations.show', $location)->with('success', 'Taak succesvol verwijderd.');
    }
}
