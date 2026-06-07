<?php

namespace App\Http\Resources;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Location $resource
 *
 * @mixin Location
 */
class LocationResource extends JsonResource
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
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            // Optioneel: relaties toevoegen als ze geladen zijn
            // 'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            // 'plannings' => PlanningResource::collection($this->whenLoaded('plannings')),
            // 'default_tasks' => DefaultTaskResource::collection($this->whenLoaded('defaultTasks')),
        ];
    }
}
