<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
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

        // Apply filter for locations with open tasks.
        // Group by the primary key so the HAVING clause runs as an aggregate query,
        // which keeps the filter portable across MySQL and SQLite.
        if ($activeFilter === 'with_open_tasks') {
            $locationsQuery->groupBy('locations.id')
                ->havingRaw('(open_tasks_high_count + open_tasks_normal_count + open_tasks_low_count) > 0');
        }

        $locationsQuery->orderBy($sortBy, $sortDirection);

        $perPage = $this->resolvePerPage($request, $locationsQuery);
        $locations = $locationsQuery->paginate($perPage)->appends($request->query()); // appends query string to pagination links

        return view($this->viewName('locations.index'), compact('locations', 'sortBy', 'sortDirection', 'searchTerm', 'activeFilter'));
    }

    /**
     * Display the specified resource.
     * Route model binding will automatically fail with 404 if a soft-deleted location is requested.
     */
    public function show(Request $request, Location $location): View
    {
        // Fetch open tasks for the location
        $openTasksQuery = $location->tasks()
            ->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::REJECTED->value])
            ->orderByRaw('(deadline IS NULL) ASC, deadline ASC') // Tasks with deadlines first, then by date
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC")
            ->orderBy('created_at', 'desc');
        $openTasksPerPage = $this->resolvePerPage($request, $openTasksQuery, 10);
        $open_tasks = $openTasksQuery->paginate($openTasksPerPage)->withQueryString();

        return view($this->viewName('locations.show'), compact('location', 'open_tasks'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(\App\Services\ExternalLocationService $externalLocationService): View
    {
        $externalLocations = $externalLocationService->fetchExternalLocations() ?? [];

        return view($this->viewName('locations.create'), compact('externalLocations'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        return redirect()->route('locations.index')
            ->with('success', 'Locatie succesvol aangemaakt.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Location $location, \App\Services\ExternalLocationService $externalLocationService): View|RedirectResponse
    {
        if (! is_null($location->external_id)) {
            return redirect()->route('locations.index')
                ->with('error', 'Gesynchroniseerde locaties kunnen niet worden gewijzigd.');
        }

        $externalLocations = $externalLocationService->fetchExternalLocations() ?? [];

        return view($this->viewName('locations.edit'), compact('location', 'externalLocations'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        if (! is_null($location->external_id)) {
            return redirect()->route('locations.index')
                ->with('error', 'Gesynchroniseerde locaties kunnen niet worden gewijzigd.');
        }

        $location->update($request->validated());

        return redirect()->route('locations.index')
            ->with('success', 'Locatie succesvol bijgewerkt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Location $location): RedirectResponse
    {
        if (! is_null($location->external_id)) {
            return redirect()->route('locations.index')
                ->with('error', 'Gesynchroniseerde locaties kunnen niet worden verwijderd.');
        }

        $location->delete();

        return redirect()->route('locations.index')
            ->with('success', 'Locatie succesvol verwijderd.');
    }
}
