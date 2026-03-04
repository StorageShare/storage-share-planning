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
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location_id' => $this->location_id,
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'deadline' => $this->deadline ? $this->deadline->toIso8601String() : null,
            'estimated_hours' => $this->estimated_hours,
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'location' => new LocationResource($this->whenLoaded('location')),
            'photos' => TaskPhotoResource::collection($this->whenLoaded('taskPhotos')),
        ];
    }
}
