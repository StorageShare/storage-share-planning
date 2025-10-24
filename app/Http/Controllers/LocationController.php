<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Location;
// Remove unused Use statements for StoreLocationRequest and UpdateLocationRequest
// if they are no longer needed after removing store/update methods.
// use App\Http\Requests\StoreLocationRequest;
// use App\Http\Requests\UpdateLocationRequest;
// use Illuminate\Http\RedirectResponse; // Might still be used by sync or other future methods
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     * Fetches only non-soft-deleted locations due to SoftDeletes trait on Location model.
     */
    public function index(Request $request): View
    {
        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $searchTerm = $request->input('search_term', '');
        $activeFilter = $request->input('filter'); // Get the active filter

        $validSortColumns = ['name', 'open_tasks_high_count', 'open_tasks_normal_count', 'open_tasks_low_count'];
        if (! in_array($sortBy, $validSortColumns)) {
            $sortBy = 'name';
        }
        if (! in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $locationsQuery = Location::query()
            ->select('locations.*')
            ->withCount([
                'tasks as open_tasks_high_count' => function ($query) {
                    $query->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::REJECTED->value])
                          ->where('priority', TaskPriority::HIGH->value);
                },
                'tasks as open_tasks_normal_count' => function ($query) {
                    $query->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::REJECTED->value])
                          ->where('priority', TaskPriority::NORMAL->value);
                },
                'tasks as open_tasks_low_count' => function ($query) {
                    $query->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::REJECTED->value])
                          ->where('priority', TaskPriority::LOW->value);
                },
            ]);

        if (! empty($searchTerm)) {
            $locationsQuery->whereRaw('LOWER(name) LIKE ?', [strtolower("%{$searchTerm}%")]);
        }

        // Apply filter for locations with open tasks
        if ($activeFilter === 'with_open_tasks') {
            $locationsQuery->havingRaw('(open_tasks_high_count + open_tasks_normal_count + open_tasks_low_count) > 0');
        }

        $locationsQuery->orderBy($sortBy, $sortDirection);

        $locations = $locationsQuery->paginate(15)->appends($request->query()); // appends query string to pagination links

        return view('locations.index', compact('locations', 'sortBy', 'sortDirection', 'searchTerm', 'activeFilter'));
    }

    /**
     * Display the specified resource.
     * Route model binding will automatically fail with 404 if a soft-deleted location is requested.
     */
    public function show(Location $location): View
    {
        // Fetch open tasks for the location
        $open_tasks = $location->tasks()
            ->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::REJECTED->value])
            ->orderByRaw('ISNULL(deadline) ASC, deadline ASC') // Tasks with deadlines first, then by date
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC")
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('locations.show', compact('location', 'open_tasks'));
    }

    // create(), store(), edit(), update(), destroy() methods are removed
    // as locations are now managed solely via API synchronization.
}
