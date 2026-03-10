<?php

namespace App\Http\Resources;

use App\Models\Planning;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Planning $resource
 * @mixin Planning
 */
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
            'id' => $this->resource->id,
            'planned_date' => $this->resource->planned_date->format('Y-m-d'), // Required by request validation
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'locations' => LocationResource::collection($this->whenLoaded('locations')),
            'planning_tasks' => PlanningTaskResource::collection($this->whenLoaded('planningTasks')),
        ];
    }
}
