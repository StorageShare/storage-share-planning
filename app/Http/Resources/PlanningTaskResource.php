<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanningTaskResource extends JsonResource
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
            'planning_id' => $this->planning_id,
            'task_id' => $this->task_id,          // ID of the original ad-hoc task, if applicable
            'default_task_id' => $this->default_task_id, // ID of the original default task, if applicable
            'title' => $this->title,            // Denormalized title
            'description' => $this->description,  // Denormalized description
            'completed_at' => $this->completed_at ? $this->completed_at->toIso8601String() : null,
            'completed_notes' => $this->completed_notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Conditionally load the original task details
            'original_task' => new TaskResource($this->whenLoaded('task')),
            'original_default_task' => new DefaultTaskResource($this->whenLoaded('defaultTask')),
            'photos' => PlanningTaskPhotoResource::collection($this->whenLoaded('planningTaskPhotos')),
            // We don't include the full Planning resource here to avoid circular dependencies
            // if PlanningResource in turn loads its PlanningTaskResource items.
        ];
    }
}
