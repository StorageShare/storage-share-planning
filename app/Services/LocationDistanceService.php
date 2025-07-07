<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationDistance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

class LocationDistanceService
{
    protected TravelTimeService $travelTimeService;

    public function __construct(TravelTimeService $travelTimeService)
    {
        $this->travelTimeService = $travelTimeService;
    }

    /**
     * Haal afstand op tussen twee locaties
     * Kijkt eerst in database, berekent alleen als nodig
     */
    public function getDistance(int $fromLocationId, int $toLocationId, bool $forceRecalculate = false): ?LocationDistance
    {
        // Check eerst of we al een recente afstand hebben
        if (!$forceRecalculate) {
            $existingDistance = LocationDistance::getDistance($fromLocationId, $toLocationId);
            if ($existingDistance && $existingDistance->isRecent(168)) { // 1 week
                return $existingDistance;
            }
        }

        // Bereken nieuwe afstand als nodig
        return $this->calculateAndStore($fromLocationId, $toLocationId);
    }

    /**
     * Bereken en sla afstand op tussen twee locaties
     */
    public function calculateAndStore(int $fromLocationId, int $toLocationId): ?LocationDistance
    {
        try {
            $fromLocation = Location::find($fromLocationId);
            $toLocation = Location::find($toLocationId);

            if (!$fromLocation || !$toLocation) {
                throw new Exception("Een of beide locaties niet gevonden: {$fromLocationId}, {$toLocationId}");
            }

            // Gebruik bestaande TravelTimeService
            $result = $this->travelTimeService->calculateTravelTimesForSequence(
                locations: [$toLocation], // Alleen de bestemming locatie
                startAddress: $fromLocation->full_address ?: $fromLocation->name
            );

            if (empty($result['segments'])) {
                Log::warning("Travel time berekening mislukt", [
                    'from_location_id' => $fromLocationId,
                    'to_location_id' => $toLocationId,
                    'result' => $result
                ]);
                return null;
            }

            $segment = $result['segments'][0];
            
            // Sla resultaat op in database
            $distance = LocationDistance::storeDistance(
                fromLocationId: $fromLocationId,
                toLocationId: $toLocationId,
                distanceKm: $segment['distance_km'] ?? null,
                durationMinutes: $segment['duration_minutes'] ?? null,
                calculationMethod: 'google_maps',
                apiResponse: $segment
            );

            // Sla ook omgekeerde richting op (vaak hetzelfde)
            LocationDistance::storeDistance(
                fromLocationId: $toLocationId,
                toLocationId: $fromLocationId,
                distanceKm: $segment['distance_km'] ?? null,
                durationMinutes: $segment['duration_minutes'] ?? null,
                calculationMethod: 'google_maps',
                apiResponse: $segment
            );

            return $distance;

        } catch (Exception $e) {
            Log::error("Fout bij berekenen afstand", [
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Haal alle afstanden op vanaf een locatie, gesorteerd op afstand
     */
    public function getDistancesFromLocation(int $fromLocationId, bool $calculateMissing = true): Collection
    {
        $distances = LocationDistance::getDistancesFrom($fromLocationId);
        
        if ($calculateMissing) {
            // Vind locaties waarvoor we nog geen afstand hebben
            $existingToLocationIds = $distances->pluck('to_location_id')->toArray();
            $allLocationIds = Location::pluck('id')->toArray();
            $missingLocationIds = array_diff($allLocationIds, $existingToLocationIds, [$fromLocationId]);

            // Bereken ontbrekende afstanden (in batches om API limits te respecteren)
            foreach (array_chunk($missingLocationIds, 5) as $batch) {
                foreach ($batch as $toLocationId) {
                    $newDistance = $this->calculateAndStore($fromLocationId, $toLocationId);
                    if ($newDistance) {
                        $distances->push($newDistance);
                    }
                    // Kleine vertraging tussen calls
                    usleep(200000); // 200ms
                }
            }

            // Sorteer opnieuw na toevoegen van nieuwe afstanden
            $distances = $distances->sortBy('distance_km');
        }

        return $distances;
    }

    /**
     * Bereken alle afstanden voor alle locatie combinaties
     * Gebruik dit voor het vooraf berekenen van alle afstanden
     */
    public function calculateAllDistances(bool $skipExisting = true): array
    {
        $locations = Location::all();
        $results = [];
        $calculated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($locations as $fromLocation) {
            foreach ($locations as $toLocation) {
                if ($fromLocation->id === $toLocation->id) {
                    continue; // Skip zelfde locatie
                }

                // Check of we deze combinatie al hebben
                if ($skipExisting) {
                    $existing = LocationDistance::getDistance($fromLocation->id, $toLocation->id);
                    if ($existing && $existing->isRecent(168)) { // 1 week
                        $skipped++;
                        continue;
                    }
                }

                $distance = $this->calculateAndStore($fromLocation->id, $toLocation->id);
                
                if ($distance) {
                    $calculated++;
                    $results[] = $distance;
                } else {
                    $errors++;
                }

                // Vertraging om API limits te respecteren
                usleep(300000); // 300ms
            }
        }

        return [
            'calculated' => $calculated,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_locations' => $locations->count(),
            'results' => $results
        ];
    }

    /**
     * Cleanup oude distance data
     */
    public function cleanupOldDistances(int $olderThanDays = 30): int
    {
        $deleted = LocationDistance::where('calculated_at', '<', now()->subDays($olderThanDays))
            ->delete();

        Log::info("Cleanup van oude location distances", [
            'deleted_count' => $deleted,
            'older_than_days' => $olderThanDays
        ]);

        return $deleted;
    }

    /**
     * Sorteer locatie IDs op afstand vanaf een referentie locatie
     */
    public function sortLocationsByDistance(int $fromLocationId, array $locationIds): array
    {
        $distances = [];

        foreach ($locationIds as $locationId) {
            if ($locationId == $fromLocationId) {
                continue; // Skip zelfde locatie
            }

            $distance = $this->getDistance($fromLocationId, $locationId);
            $distances[$locationId] = $distance ? $distance->distance_km : 9999; // Hoge waarde voor onbekende afstanden
        }

        // Sorteer op afstand
        asort($distances);

        return array_keys($distances);
    }

    /**
     * API endpoint data voor frontend
     */
    public function getDistancesForApi(int $fromLocationId): array
    {
        $distances = $this->getDistancesFromLocation($fromLocationId, false);
        
        return $distances->map(function ($distance) {
            return [
                'to_location_id' => $distance->to_location_id,
                'location_name' => $distance->toLocation->name ?? 'Onbekend',
                'distance_km' => $distance->distance_km,
                'duration_minutes' => $distance->duration_minutes,
                'formatted_distance' => $distance->formatted_distance,
                'formatted_duration' => $distance->formatted_duration,
                'calculated_at' => $distance->calculated_at?->toISOString(),
                'is_recent' => $distance->isRecent(),
            ];
        })->toArray();
    }
} 