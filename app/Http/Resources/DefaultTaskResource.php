<?php

namespace App\Http\Resources;

use App\Models\DefaultTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read DefaultTask $resource
 *
 * @mixin DefaultTask
 */
class DefaultTaskResource extends JsonResource
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
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            // Optioneel: relaties toevoegen als ze geladen zijn
            // 'locations' => LocationResource::collection($this->whenLoaded('locations')),
        ];
    }
}
