<?php

namespace App\Http\Controllers;

use App\Enums\VehicleType;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Vehicle::orderBy('name');
        $perPage = $this->resolvePerPage($request, $query);
        $vehicles = $query->paginate($perPage)->withQueryString();

        return view($this->viewName('vehicles.index'), compact('vehicles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $types = VehicleType::options();
        return view($this->viewName('vehicles.create'), compact('types'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        try {
            Vehicle::create($request->validated());
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle potential DB-level unique constraint violations gracefully
            if ((string) $e->getCode() === '23000') {
                return back()
                    ->withInput()
                    ->withErrors(['license_number' => 'Dit kenteken is al in gebruik.']);
            }
            throw $e;
        }

        return redirect()->route('vehicles.index')->with('status', 'Voertuig succesvol aangemaakt.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle): View
    {
        $types = VehicleType::options();
        return view($this->viewName('vehicles.edit'), compact('vehicle', 'types'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($request->validated());

        return redirect()->route('vehicles.index')->with('status', 'Voertuig succesvol bijgewerkt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->delete();

        return redirect()->route('vehicles.index')->with('status', 'Voertuig succesvol verwijderd.');
    }
}
