<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequirementRequest;
use App\Http\Requests\UpdateRequirementRequest;
use App\Models\Requirement;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RequirementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Requirement::with(['creator', 'requiredForLocations'])
            ->orderBy('name');
        $perPage = $this->resolvePerPage($request, $query, 20);
        $requirements = $query->paginate($perPage)->withQueryString();

        return view('requirements.index', compact('requirements'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $locations = Location::orderBy('name')->get();

        return view('requirements.create', compact('locations'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequirementRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();

        $requirement = Requirement::create($validated);

        // Sync the required locations
        if (!empty($validated['required_for_locations'])) {
            $requirement->requiredForLocations()->sync($validated['required_for_locations']);
        }

        return redirect()->route('requirements.index')
            ->with('success', 'Requirement succesvol aangemaakt.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Requirement $requirement): View
    {
        $requirement->load(['creator', 'tasks.location', 'defaultTasks', 'requiredForLocations']);

        return view('requirements.show', compact('requirement'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Requirement $requirement): View
    {
        $locations = Location::orderBy('name')->get();
        $selectedLocations = $requirement->requiredForLocations->pluck('id')->toArray();

        return view('requirements.edit', compact('requirement', 'locations', 'selectedLocations'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequirementRequest $request, Requirement $requirement): RedirectResponse
    {
        $validated = $request->validated();

        $requirement->update($validated);

        // Sync the required locations
        $requirement->requiredForLocations()->sync($validated['required_for_locations'] ?? []);

        return redirect()->route('requirements.index')
            ->with('success', 'Requirement succesvol bijgewerkt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Requirement $requirement): RedirectResponse
    {
        $requirement->delete();

        return redirect()->route('requirements.index')
            ->with('success', 'Requirement succesvol verwijderd.');
    }
}
