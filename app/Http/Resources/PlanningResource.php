<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanningResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'planned_date' => $this->planned_date->format('Y-m-d'), // Format date as needed
            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'location' => new LocationResource($this->whenLoaded('location')),
            'planning_tasks' => PlanningTaskResource::collection($this->whenLoaded('planningTasks')),
        ];
    }
}
