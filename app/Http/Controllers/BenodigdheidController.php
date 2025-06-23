<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBenodigdheidRequest;
use App\Http\Requests\UpdateBenodigdheidRequest;
use App\Models\Benodigdheid;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BenodigdheidController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $benodigdheden = Benodigdheid::with(['creator', 'requiredForLocations'])
            ->orderBy('naam')
            ->paginate(20);

        return view('benodigdheden.index', compact('benodigdheden'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $locations = Location::orderBy('name')->get();
        
        return view('benodigdheden.create', compact('locations'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBenodigdheidRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();

        $benodigdheid = Benodigdheid::create($validated);

        // Sync the required locations
        if (!empty($validated['required_for_locations'])) {
            $benodigdheid->requiredForLocations()->sync($validated['required_for_locations']);
        }

        return redirect()->route('benodigdheden.index')
            ->with('success', 'Benodigdheid succesvol aangemaakt.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Benodigdheid $benodigdheden): View
    {
        $benodigdheden->load(['creator', 'tasks.location', 'defaultTasks', 'requiredForLocations']);

        return view('benodigdheden.show', compact('benodigdheden'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Benodigdheid $benodigdheden): View
    {
        $locations = Location::orderBy('name')->get();
        $selectedLocations = $benodigdheden->requiredForLocations->pluck('id')->toArray();
        
        return view('benodigdheden.edit', compact('benodigdheden', 'locations', 'selectedLocations'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBenodigdheidRequest $request, Benodigdheid $benodigdheden): RedirectResponse
    {
        $validated = $request->validated();
        
        $benodigdheden->update($validated);

        // Sync the required locations
        $benodigdheden->requiredForLocations()->sync($validated['required_for_locations'] ?? []);

        return redirect()->route('benodigdheden.index')
            ->with('success', 'Benodigdheid succesvol bijgewerkt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Benodigdheid $benodigdheden): RedirectResponse
    {
        $benodigdheden->delete();

        return redirect()->route('benodigdheden.index')
            ->with('success', 'Benodigdheid succesvol verwijderd.');
    }
}
