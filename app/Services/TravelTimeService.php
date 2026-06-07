<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TravelTimeService
{
    /**
     * Calculate travel time between two locations in minutes
     *
     * @param  Location|string  $origin
     * @param  Location|string  $destination
     * @return array{duration_minutes:int, distance_km:float, duration_text?:string, distance_text?:string, error?:string, estimated?:bool}
     */
    public function calculateTravelTime($origin, $destination, string $mode = 'driving'): array
    {
        $originAddress = $this->getAddressFromLocation($origin);
        $destinationAddress = $this->getAddressFromLocation($destination);

        if (empty($originAddress) || empty($destinationAddress)) {
            return [
                'duration_minutes' => 0,
                'distance_km' => 0,
                'error' => 'Onvoldoende adresgegevens beschikbaar',
            ];
        }

        $cacheKey = $this->getCacheKey($originAddress, $destinationAddress, $mode);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($originAddress, $destinationAddress, $mode) {
            return $this->fetchTravelTimeFromApi($originAddress, $destinationAddress, $mode);
        });
    }

    /**
     * Calculate travel times for a sequence of locations
     *
     * @param  array<int, Location|string>  $locations
     * @return array{
     *   segments: array<int, array{from:string, to:string, duration_minutes:int, distance_km:float, index:int|string, error?:string, is_return?:bool}>,
     *   total_duration_minutes:int,
     *   total_duration_formatted:string
     * }
     */
    public function calculateTravelTimesForSequence(array $locations, ?string $startAddress = null, string $mode = 'driving'): array
    {
        $results = [];
        $previousLocation = $startAddress;
        $totalTime = 0;

        // Travel to each location
        foreach ($locations as $index => $location) {
            if ($previousLocation) {
                $travelTime = $this->calculateTravelTime($previousLocation, $location, $mode);
                $results[] = [
                    'from' => $this->getDisplayName($previousLocation),
                    'to' => $this->getDisplayName($location),
                    'duration_minutes' => $travelTime['duration_minutes'],
                    'distance_km' => $travelTime['distance_km'],
                    'index' => $index,
                    'error' => $travelTime['error'] ?? null,
                ];
                $totalTime += $travelTime['duration_minutes'];
            }
            $previousLocation = $location;
        }

        // Add return trip to start address if we have a start address and locations
        if ($startAddress && ! empty($locations) && $previousLocation) {
            $returnTravelTime = $this->calculateTravelTime($previousLocation, $startAddress, $mode);
            $results[] = [
                'from' => $this->getDisplayName($previousLocation),
                'to' => $this->getDisplayName($startAddress),
                'duration_minutes' => $returnTravelTime['duration_minutes'],
                'distance_km' => $returnTravelTime['distance_km'],
                'index' => 'return',
                'error' => $returnTravelTime['error'] ?? null,
                'is_return' => true,
            ];
            $totalTime += $returnTravelTime['duration_minutes'];
        }

        return [
            'segments' => $results,
            'total_duration_minutes' => $totalTime,
            'total_duration_formatted' => $this->formatDuration((int) $totalTime),
        ];
    }

    /**
     * Get address string from location or string
     */
    private function getAddressFromLocation(Location|string $location): string
    {
        if ($location instanceof Location) {
            return $location->full_address ?: $location->name;
        }

        return (string) $location;
    }

    /**
     * Get display name from location or string
     */
    private function getDisplayName(Location|string $location): string
    {
        if ($location instanceof Location) {
            return $location->name;
        }

        return (string) $location;
    }

    /**
     * Generate cache key for travel time calculation
     */
    private function getCacheKey(string $origin, string $destination, string $mode): string
    {
        return 'travel_time_'.md5($origin.'_'.$destination.'_'.$mode);
    }

    /**
     * Fetch travel time from external API
     *
     * @return array{duration_minutes:int, distance_km:float, duration_text?:string, distance_text?:string, error?:string, estimated?:bool}
     */
    private function fetchTravelTimeFromApi(string $origin, string $destination, string $mode): array
    {
        try {
            // Check if Google Maps API key is configured
            $apiKey = config('services.google_maps.api_key');
            if (empty($apiKey)) {
                return $this->getEstimatedTravelTime($origin, $destination);
            }

            // First try the new Routes API
            $routesResult = $this->tryRoutesApi($origin, $destination, $mode, $apiKey);
            if ($routesResult !== null) {
                return $routesResult;
            }

            // Fallback to Distance Matrix API if Routes API fails
            $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins' => $origin,
                'destinations' => $destination,
                'mode' => $mode,
                'units' => 'metric',
                'key' => $apiKey,
                'language' => 'nl',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'OK' &&
                    isset($data['rows'][0]['elements'][0]) &&
                    $data['rows'][0]['elements'][0]['status'] === 'OK') {

                    $element = $data['rows'][0]['elements'][0];

                    return [
                        'duration_minutes' => (int) ceil($element['duration']['value'] / 60),
                        'distance_km' => round($element['distance']['value'] / 1000, 1),
                        'duration_text' => $element['duration']['text'],
                        'distance_text' => $element['distance']['text'],
                    ];
                }
            }

            $apiResponse = $response->json();

            Log::warning('Google Maps API error', [
                'origin' => $origin,
                'destination' => $destination,
                'response' => $apiResponse,
            ]);

            // Check for specific API activation error
            if (isset($apiResponse['status']) && $apiResponse['status'] === 'REQUEST_DENIED') {
                $estimatedTime = str_contains(strtolower($origin.$destination), 'amsterdam') ? 15 : 25;

                return [
                    'duration_minutes' => $estimatedTime,
                    'distance_km' => 0,
                    'error' => 'Legacy API niet ondersteund - Routes API vereist',
                    'estimated' => true,
                ];
            }

            return $this->getEstimatedTravelTime($origin, $destination);

        } catch (\Exception $e) {
            Log::error('Travel time calculation failed', [
                'origin' => $origin,
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);

            return $this->getEstimatedTravelTime($origin, $destination);
        }
    }

    /**
     * Try the new Google Routes API
     *
     * @return array{duration_minutes:int, distance_km:float, duration_text?:string, distance_text?:string, error?:string, estimated?:bool}|null
     */
    private function tryRoutesApi(string $origin, string $destination, string $mode, string $apiKey): ?array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters',
            ])->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
                'origin' => [
                    'address' => $origin,
                ],
                'destination' => [
                    'address' => $destination,
                ],
                'travelMode' => strtoupper($mode === 'driving' ? 'DRIVE' : $mode),
                'routingPreference' => 'TRAFFIC_AWARE',
                'units' => 'METRIC',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['routes'][0])) {
                    $route = $data['routes'][0];
                    $durationSeconds = (int) str_replace('s', '', $route['duration'] ?? '0s');
                    $distanceMeters = $route['distanceMeters'] ?? 0;

                    return [
                        'duration_minutes' => (int) ceil($durationSeconds / 60),
                        'distance_km' => round($distanceMeters / 1000, 1),
                        'duration_text' => $this->formatDuration((int) ceil($durationSeconds / 60)),
                        'distance_text' => round($distanceMeters / 1000, 1).' km',
                    ];
                }
            }

            // Log Routes API error for debugging
            if ($response->status() >= 400) {
                Log::info('Routes API failed, falling back to Distance Matrix', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }

            return null; // Fall back to Distance Matrix API

        } catch (\Exception $e) {
            Log::info('Routes API exception, falling back to Distance Matrix', [
                'error' => $e->getMessage(),
            ]);

            return null; // Fall back to Distance Matrix API
        }
    }

    /**
     * Get estimated travel time when API is not available
     *
     * @return array{duration_minutes:int, distance_km:float, error:string, estimated:bool}
     */
    private function getEstimatedTravelTime(string $origin, string $destination): array
    {
        // Simple estimation: 15 minutes for local travel, 30 minutes for longer distances
        $estimatedTime = str_contains(strtolower($origin.$destination), 'amsterdam') ? 15 : 25;

        return [
            'duration_minutes' => $estimatedTime,
            'distance_km' => 0.0,
            'error' => 'Geschatte reistijd (geen API-sleutel beschikbaar)',
            'estimated' => true,
        ];
    }

    /**
     * Format duration in minutes to human readable format
     */
    public function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours.' uur';
        }

        return $hours.' uur '.$remainingMinutes.' min';
    }
}
