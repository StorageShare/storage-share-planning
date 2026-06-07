<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\TravelTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TravelTimeController extends Controller
{
    public function __construct(
        private TravelTimeService $travelTimeService
    ) {}

    /**
     * Calculate travel time between two locations
     */
    public function calculate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'origin' => 'required|string',
                'destination' => 'required|string',
                'mode' => 'nullable|string|in:driving,walking,transit',
            ]);

            $result = $this->travelTimeService->calculateTravelTime(
                $validated['origin'],
                $validated['destination'],
                $validated['mode'] ?? 'driving'
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validatiefout',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fout bij berekenen reistijd',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate travel times for a sequence of locations
     */
    public function calculateSequence(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'location_ids' => 'required|array|min:1',
                'location_ids.*' => 'required|integer|exists:locations,id',
                'start_address' => 'nullable|string',
                'mode' => 'nullable|string|in:driving,walking,transit',
            ]);

            $locations = Location::whereIn('id', $validated['location_ids'])
                ->get()
                ->sortBy(function ($location) use ($validated) {
                    return array_search($location->id, $validated['location_ids']);
                })
                ->values(); // reindex to ensure 0..n keys in correct order

            $result = $this->travelTimeService->calculateTravelTimesForSequence(
                $locations->all(),
                $validated['start_address'] ?? null,
                $validated['mode'] ?? 'driving'
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validatiefout',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fout bij berekenen reistijden',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
