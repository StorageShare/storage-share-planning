<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationDistance;
use App\Services\LocationDistanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LocationDistanceController extends Controller
{
    protected LocationDistanceService $locationDistanceService;

    public function __construct(LocationDistanceService $locationDistanceService)
    {
        $this->locationDistanceService = $locationDistanceService;
    }

    /**
     * Haal gesorteerde afstanden op vanaf een locatie
     *
     * GET /api/v1/location-distances/{locationId}/sorted
     */
    public function getSortedDistances(int $locationId): JsonResponse
    {
        try {
            $location = Location::find($locationId);
            if (! $location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Locatie niet gevonden',
                ], 404);
            }

            $distances = $this->locationDistanceService->getDistancesForApi($locationId);

            return response()->json([
                'success' => true,
                'data' => [
                    'from_location' => [
                        'id' => $location->id,
                        'name' => $location->name,
                    ],
                    'distances' => $distances,
                    'total_count' => count($distances),
                    'cached_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting sorted distances', [
                'location_id' => $locationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fout bij ophalen afstanden',
            ], 500);
        }
    }

    /**
     * Sorteer een lijst van locatie IDs op afstand
     *
     * POST /api/v1/location-distances/sort
     * Body: {
     *   "from_location_id": 1,
     *   "location_ids": [2, 3, 4, 5]
     * }
     */
    public function sortLocationsByDistance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_location_id' => 'required|integer|exists:locations,id',
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => 'integer|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validatie mislukt',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $fromLocationId = $request->input('from_location_id');
            $locationIds = $request->input('location_ids');

            // Verwijder de from_location uit de lijst als deze erin zit
            $locationIds = array_filter($locationIds, fn ($id) => $id != $fromLocationId);

            $sortedIds = $this->locationDistanceService->sortLocationsByDistance($fromLocationId, $locationIds);

            // Haal extra info op voor de response
            $locationsWithDistances = [];
            foreach ($sortedIds as $locationId) {
                $distance = $this->locationDistanceService->getDistance($fromLocationId, $locationId);
                $location = Location::find($locationId);

                $locationsWithDistances[] = [
                    'location_id' => $locationId,
                    'location_name' => $location->name ?? 'Onbekend',
                    'distance_km' => $distance?->distance_km,
                    'duration_minutes' => $distance->duration_minutes,
                    'formatted_distance' => $distance->formatted_distance ?? 'Onbekend',
                    'formatted_duration' => $distance->formatted_duration ?? 'Onbekend',
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'from_location_id' => $fromLocationId,
                    'sorted_location_ids' => $sortedIds,
                    'locations_with_distances' => $locationsWithDistances,
                    'total_count' => count($sortedIds),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error sorting locations by distance', [
                'from_location_id' => $request->input('from_location_id'),
                'location_ids' => $request->input('location_ids'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fout bij sorteren locaties',
            ], 500);
        }
    }

    /**
     * Haal afstand op tussen twee specifieke locaties
     *
     * GET /api/v1/location-distances/{fromLocationId}/to/{toLocationId}
     */
    public function getDistanceBetween(int $fromLocationId, int $toLocationId): JsonResponse
    {
        try {
            $fromLocation = Location::find($fromLocationId);
            $toLocation = Location::find($toLocationId);

            if (! $fromLocation || ! $toLocation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Een of beide locaties niet gevonden',
                ], 404);
            }

            $distance = $this->locationDistanceService->getDistance($fromLocationId, $toLocationId);

            if (! $distance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Afstand kon niet worden berekend',
                    'data' => [
                        'from_location' => ['id' => $fromLocationId, 'name' => $fromLocation->name],
                        'to_location' => ['id' => $toLocationId, 'name' => $toLocation->name],
                    ],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'from_location' => ['id' => $fromLocationId, 'name' => $fromLocation->name],
                    'to_location' => ['id' => $toLocationId, 'name' => $toLocation->name],
                    'distance_km' => $distance->distance_km,
                    'duration_minutes' => $distance->duration_minutes,
                    'formatted_distance' => $distance->formatted_distance,
                    'formatted_duration' => $distance->formatted_duration,
                    'calculated_at' => $distance->calculated_at?->toISOString(),
                    'is_recent' => $distance->isRecent(),
                    'calculation_method' => $distance->calculation_method,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting distance between locations', [
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fout bij ophalen afstand',
            ], 500);
        }
    }

    /**
     * Forceer herberekening van afstand tussen twee locaties
     *
     * POST /api/v1/location-distances/{fromLocationId}/to/{toLocationId}/recalculate
     */
    public function recalculateDistance(int $fromLocationId, int $toLocationId): JsonResponse
    {
        try {
            $fromLocation = Location::find($fromLocationId);
            $toLocation = Location::find($toLocationId);

            if (! $fromLocation || ! $toLocation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Een of beide locaties niet gevonden',
                ], 404);
            }

            $distance = $this->locationDistanceService->getDistance($fromLocationId, $toLocationId, true);

            if (! $distance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Afstand kon niet worden herberekend',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Afstand succesvol herberekend',
                'data' => [
                    'from_location' => ['id' => $fromLocationId, 'name' => $fromLocation->name],
                    'to_location' => ['id' => $toLocationId, 'name' => $toLocation->name],
                    'distance_km' => $distance->distance_km,
                    'duration_minutes' => $distance->duration_minutes,
                    'formatted_distance' => $distance->formatted_distance,
                    'formatted_duration' => $distance->formatted_duration,
                    'calculated_at' => $distance->calculated_at?->toISOString(),
                    'calculation_method' => $distance->calculation_method,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error recalculating distance', [
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fout bij herberekenen afstand',
            ], 500);
        }
    }

    /**
     * Haal cache statistieken op
     *
     * GET /api/v1/location-distances/stats
     */
    public function getCacheStats(): JsonResponse
    {
        try {
            $totalDistances = LocationDistance::count();
            $recentDistances = LocationDistance::recent(24)->count();
            $oldDistances = LocationDistance::where('calculated_at', '<', now()->subDays(365))->count();
            $totalLocations = Location::count();
            $maxPossibleDistances = $totalLocations * ($totalLocations - 1); // A→B en B→A zijn apart

            $coveragePercentage = $maxPossibleDistances > 0 ?
                round(($totalDistances / $maxPossibleDistances) * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_cached_distances' => $totalDistances,
                    'recent_distances' => $recentDistances,
                    'old_distances' => $oldDistances,
                    'total_locations' => $totalLocations,
                    'max_possible_distances' => $maxPossibleDistances,
                    'coverage_percentage' => $coveragePercentage,
                    'cache_stats' => [
                        'recent_threshold_hours' => 24,
                        'old_threshold_days' => 365,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting cache stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fout bij ophalen cache statistieken',
            ], 500);
        }
    }
}
