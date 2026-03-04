<?php

namespace App\Http\Resources;

use App\Models\PlanningTaskPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @property-read PlanningTaskPhoto $resource
 * @mixin PlanningTaskPhoto
 */
class PlanningTaskPhotoResource extends JsonResource
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
            'planning_task_id' => $this->planning_task_id,
            // De 'url' accessor wordt automatisch toegevoegd dankzij $appends in het model
            'url' => $this->url, // Accessor: Storage::url($this->path)
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
