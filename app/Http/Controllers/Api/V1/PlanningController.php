<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanningRequest;
use App\Http\Requests\UpdatePlanningRequest;
use App\Http\Resources\PlanningResource;
use App\Models\DefaultTask;
use App\Models\Planning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PlanningController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $plannings = Planning::with(['location', 'planningTasks', 'planningTasks.task', 'planningTasks.defaultTask'])
            ->latest()
            ->paginate(10);

        return PlanningResource::collection($plannings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlanningRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            DB::beginTransaction();

            $planning = Planning::create([
                'location_id' => $validatedData['location_id'],
                'planned_date' => $validatedData['planned_date'],
                'notes' => $validatedData['notes'] ?? null,
            ]);

            // Verwerk geselecteerde standaardtaken
            if (! empty($validatedData['selected_default_tasks'])) {
                $defaultTasks = DefaultTask::findMany($validatedData['selected_default_tasks']);
                foreach ($defaultTasks as $defaultTask) {
                    $planning->planningTasks()->create([
                        'default_task_id' => $defaultTask->id,
                        'title' => $defaultTask->title,
                        'description' => $defaultTask->description,
                    ]);
                }
            }

            // Verwerk ad-hoc taken
            if (! empty($validatedData['adhoc_tasks'])) {
                foreach ($validatedData['adhoc_tasks'] as $adhocTaskData) {
                    $planning->planningTasks()->create([
                        'title' => $adhocTaskData['title'],
                        'description' => $adhocTaskData['description'],
                    ]);
                }
            }

            DB::commit();

            // Laad relaties voor de response
            $planning->load(['location', 'planningTasks', 'planningTasks.task', 'planningTasks.defaultTask']);

            return (new PlanningResource($planning))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();

            // Log::error('API Error creating planning: ' . $e->getMessage());
            return response()->json([
                'message' => 'Fout bij het aanmaken van de planning.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Planning $planning): PlanningResource
    {
        $planning->load(['location', 'planningTasks', 'planningTasks.task', 'planningTasks.defaultTask', 'planningTasks.task.taskPhotos']);

        return new PlanningResource($planning);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlanningRequest $request, Planning $planning): PlanningResource
    {
        $planning->update($request->validated());
        $planning->load(['location', 'planningTasks', 'planningTasks.task', 'planningTasks.defaultTask']);

        return new PlanningResource($planning);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Planning $planning): JsonResponse
    {
        $planning->delete(); // PlanningTasks worden mee verwijderd via cascade

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
