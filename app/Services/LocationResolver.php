<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Validation\ValidationException;

class LocationResolver
{
    /**
     * Resolve the target location by internal id or by external id.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function resolve(array $data): Location
    {
        if (! empty($data['location_id'])) {
            return Location::findOrFail($data['location_id']);
        }

        $externalId = $data['location_external_id'] ?? null;
        if ($externalId === null) {
            throw ValidationException::withMessages([
                'location_id' => 'Location is required.',
            ]);
        }

        $location = Location::query()
            ->where('external_id', $externalId)
            ->orWhere('sync_external_id', $externalId)
            ->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'location_external_id' => 'No location found for the provided external id.',
            ]);
        }

        return $location;
    }
}
