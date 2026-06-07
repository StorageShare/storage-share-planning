<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDefaultVehicleTaskRequest;
use App\Http\Requests\UpdateDefaultVehicleTaskRequest;
use App\Models\DefaultVehicleTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DefaultVehicleTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $validSort = ['title', 'created_at'];
        if (! in_array($sortBy, $validSort, true)) {
            $sortBy = 'created_at';
        }

        $query = DefaultVehicleTask::query();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $this->resolvePerPage($request, $query);
        $defaults = $query->paginate($perPage)->appends($request->query());

        return view($this->viewName('default-vehicle-tasks.index'), compact('defaults', 'search', 'sortBy', 'sortDirection'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view($this->viewName('default-vehicle-tasks.create'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDefaultVehicleTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['active'] = $request->has('active');

        DefaultVehicleTask::create($data);

        return redirect()->route('default-vehicle-tasks.index')
            ->with('success', 'Voertuig standaardtaak succesvol aangemaakt.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DefaultVehicleTask $defaultVehicleTask): View
    {
        return view($this->viewName('default-vehicle-tasks.edit'), compact('defaultVehicleTask'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDefaultVehicleTaskRequest $request, DefaultVehicleTask $defaultVehicleTask): RedirectResponse
    {
        $data = $request->validated();
        $data['active'] = $request->has('active');
        $defaultVehicleTask->update($data);

        return redirect()->route('default-vehicle-tasks.index')
            ->with('success', 'Voertuig standaardtaak succesvol bijgewerkt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DefaultVehicleTask $defaultVehicleTask): RedirectResponse
    {
        $defaultVehicleTask->delete();

        return redirect()->route('default-vehicle-tasks.index')
            ->with('success', 'Voertuig standaardtaak succesvol verwijderd.');
    }

    /**
     * Return active default vehicle tasks as JSON for quick selection in UI.
     */
    public function active(Request $request): JsonResponse
    {
        $defaults = DefaultVehicleTask::where('active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'estimated_time_minutes']);

        return response()->json([
            'data' => $defaults,
        ]);
    }
}
