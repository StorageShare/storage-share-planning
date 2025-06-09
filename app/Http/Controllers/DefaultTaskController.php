<?php

namespace App\Http\Controllers;

use App\Models\DefaultTask;
use App\Http\Requests\StoreDefaultTaskRequest;
use App\Http\Requests\UpdateDefaultTaskRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DefaultTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $searchTerm = $request->input('search_term', '');
        $activeFilter = null;

        $validSortColumns = ['title', 'created_at'];
        if (!in_array($sortBy, $validSortColumns)) {
            $sortBy = 'created_at';
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $defaultTasksQuery = DefaultTask::query();

        if (!empty($searchTerm)) {
            $defaultTasksQuery->where(function ($query) use ($searchTerm) {
                $query->where('title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        $defaultTasksQuery->orderBy($sortBy, $sortDirection);

        $defaultTasks = $defaultTasksQuery->paginate(15)->appends($request->query());

        return view('default-tasks.index', compact(
            'defaultTasks',
            'sortBy',
            'sortDirection',
            'searchTerm',
            'activeFilter'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $locations = Location::orderBy('name')->get();
        return view('default-tasks.create', compact('locations'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDefaultTaskRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();
        $validatedData['created_by'] = auth()->id();
        
        $defaultTask = DefaultTask::create($validatedData);

        if (!empty($validatedData['locations'])) {
            $defaultTask->locations()->sync($validatedData['locations']);
        }

        return redirect()->route('default-tasks.index')->with('success', 'Standaardtaak succesvol aangemaakt.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DefaultTask $defaultTask): View
    {
        $defaultTask->load('locations');
        return view('default-tasks.show', compact('defaultTask'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DefaultTask $defaultTask): View
    {
        $locations = Location::orderBy('name')->get();
        $selectedLocations = $defaultTask->locations()->pluck('locations.id')->toArray();
        return view('default-tasks.edit', compact('defaultTask', 'locations', 'selectedLocations'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDefaultTaskRequest $request, DefaultTask $defaultTask): RedirectResponse
    {
        $validatedData = $request->validated();
        $defaultTask->update($validatedData);

        $defaultTask->locations()->sync($validatedData['locations'] ?? []);

        return redirect()->route('default-tasks.index')->with('success', 'Standaardtaak succesvol bijgewerkt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DefaultTask $defaultTask): RedirectResponse
    {
        $defaultTask->delete();
        return redirect()->route('default-tasks.index')->with('success', 'Standaardtaak succesvol verwijderd.');
    }
}
