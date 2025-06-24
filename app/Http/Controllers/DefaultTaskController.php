<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDefaultTaskRequest;
use App\Http\Requests\UpdateDefaultTaskRequest;
use App\Models\DefaultTask;
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
        if (! in_array($sortBy, $validSortColumns)) {
            $sortBy = 'created_at';
        }
        if (! in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $defaultTasksQuery = DefaultTask::query();

        if (! empty($searchTerm)) {
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
        $benodigdheden = \App\Models\Benodigdheid::orderBy('naam')->get();
        $availableDoorTypes = DefaultTask::getAvailableDoorTypes();

        return view('default-tasks.create', compact('locations', 'benodigdheden', 'availableDoorTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDefaultTaskRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();
        $validatedData['created_by'] = auth()->id();

        // Ensure boolean fields are set correctly if not present in the request
        $validatedData['applies_to_all_locations'] = $request->has('applies_to_all_locations');
        $validatedData['applies_to_lift_locations'] = $request->has('applies_to_lift_locations');
        $validatedData['applies_to_door_types'] = $request->has('applies_to_door_types');

        // Sanitize door types (hoofdletter ongevoelig)
        if (!empty($validatedData['door_types'])) {
            $validatedData['door_types'] = array_map('trim', array_map('strtolower', $validatedData['door_types']));
        }

        $defaultTask = DefaultTask::create($validatedData);

        // Handle location assignments based on different criteria
        if ($validatedData['applies_to_all_locations']) {
            // Sync met alle bestaande locaties
            $allLocationIds = Location::pluck('id')->toArray();
            $defaultTask->locations()->sync($allLocationIds);
        } elseif ($validatedData['applies_to_lift_locations']) {
            // Dit wordt afgehandeld door de DefaultTaskObserver
        } elseif ($validatedData['applies_to_door_types'] && !empty($validatedData['door_types'])) {
            // Sync met locaties die de geselecteerde deur types hebben
            $matchingLocationIds = $defaultTask->applicableLocationsByDoorType()->pluck('id')->toArray();
            $defaultTask->locations()->sync($matchingLocationIds);
        } elseif (!empty($validatedData['locations'])) {
            // Sync met specifiek geselecteerde locaties
            $defaultTask->locations()->sync($validatedData['locations']);
        }

        if (! empty($validatedData['benodigdheden'])) {
            $defaultTask->benodigdheden()->sync($validatedData['benodigdheden']);
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
        $benodigdheden = \App\Models\Benodigdheid::orderBy('naam')->get();
        $selectedBenodigdheden = $defaultTask->benodigdheden->pluck('id')->toArray();
        $availableDoorTypes = DefaultTask::getAvailableDoorTypes();

        return view('default-tasks.edit', compact('defaultTask', 'locations', 'selectedLocations', 'benodigdheden', 'selectedBenodigdheden', 'availableDoorTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDefaultTaskRequest $request, DefaultTask $defaultTask): RedirectResponse
    {
        $validatedData = $request->validated();

        // Ensure boolean fields are set correctly if not present in the request
        $validatedData['applies_to_all_locations'] = $request->has('applies_to_all_locations');
        $validatedData['applies_to_lift_locations'] = $request->has('applies_to_lift_locations');
        $validatedData['applies_to_door_types'] = $request->has('applies_to_door_types');
        
        // Sanitize door types (hoofdletter ongevoelig)
        if (!empty($validatedData['door_types'])) {
            $validatedData['door_types'] = array_map('trim', array_map('strtolower', $validatedData['door_types']));
        }
        
        $defaultTask->update($validatedData);

        // Handle location assignments based on different criteria
        if ($validatedData['applies_to_all_locations']) {
            // Sync met alle bestaande locaties
            $allLocationIds = Location::pluck('id')->toArray();
            $defaultTask->locations()->sync($allLocationIds);
        } elseif ($validatedData['applies_to_lift_locations']) {
            // De observer zal de synchronisatie afhandelen
        } elseif ($validatedData['applies_to_door_types'] && !empty($validatedData['door_types'])) {
            // Sync met locaties die de geselecteerde deur types hebben
            $matchingLocationIds = $defaultTask->applicableLocationsByDoorType()->pluck('id')->toArray();
            $defaultTask->locations()->sync($matchingLocationIds);
        } else {
            // Sync met specifiek geselecteerde locaties
            $defaultTask->locations()->sync($validatedData['locations'] ?? []);
        }
        
        $defaultTask->benodigdheden()->sync($validatedData['benodigdheden'] ?? []);

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
