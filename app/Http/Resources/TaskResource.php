<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Task $resource
 * @mixin Task
 */
class TaskResource extends JsonResource
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
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'location_id' => $this->resource->location_id,
            'priority' => $this->resource->priority->value,
            'priority_label' => $this->resource->priority->label(),
            'deadline' => $this->resource->deadline?->toIso8601String(),
            // Derive hours from minutes to avoid accessing an undefined property on Task model
            'estimated_hours' => $this->resource->estimated_time_minutes !== null
                ? round($this->resource->estimated_time_minutes / 60, 2)
                : null,
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'location' => new LocationResource($this->whenLoaded('location')),
            'photos' => TaskPhotoResource::collection($this->whenLoaded('taskPhotos')),
        ];
    }
}
