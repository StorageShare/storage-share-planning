<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalLocationService
{
    /**
     * Fetch external locations from the API.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function fetchExternalLocations(): ?array
    {
        $apiUrl = Config::get('services.external_locations_api.url');
        $apiToken = Config::get('services.external_locations_api.token');

        if (empty($apiUrl) || empty($apiToken)) {
            Log::error('ExternalLocationService: API URL or Token not configured.');
            return null;
        }

        try {
            $response = Http::withToken($apiToken)->acceptJson()->get($apiUrl);

            if (! $response->successful()) {
                Log::error('ExternalLocationService: API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $rawResponseData = $response->json();

            // 1. Check for 'spaces' key
            if (isset($rawResponseData['spaces']) && is_array($rawResponseData['spaces'])) {
                return $rawResponseData['spaces'];
            }
            // 2. Check for 'data' key
            elseif (isset($rawResponseData['data']) && is_array($rawResponseData['data'])) {
                return $rawResponseData['data'];
            }
            // 3. Check if the raw response itself is the array of locations
            elseif (is_array($rawResponseData) && ! empty($rawResponseData) && isset($rawResponseData[0]['id']) && isset($rawResponseData[0]['name'])) {
                return $rawResponseData;
            }
            // 4. Handle empty array
            elseif (is_array($rawResponseData) && empty($rawResponseData)) {
                return [];
            }

            Log::error('ExternalLocationService: Failed to extract locations array from API response.', [
                'response_body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('ExternalLocationService: Unexpected error.', ['exception' => $e]);
            return null;
        }
    }

    /**
     * Fetch inactive rooms for a specific external space from the API.
     *
     * @param string|int $externalId
     * @return array<int, string>|null
     */
    public function fetchInactiveRooms($externalId): ?array
    {
        $baseUrl = Config::get('services.external_locations_api.url');
        $apiToken = Config::get('services.external_locations_api.token');

        // The base URL is likely ".../api/spaces", we want ".../api/spaces/{id}/inactive-rooms"
        $apiUrl = dirname($baseUrl) . '/spaces/' . $externalId . '/inactive-rooms';

        if (empty($baseUrl) || empty($apiToken)) {
            Log::error('ExternalLocationService: API URL or Token not configured.');
            return null;
        }

        try {
            $response = Http::withToken($apiToken)->acceptJson()->get($apiUrl);

            if (! $response->successful()) {
                Log::error('ExternalLocationService: Inactive rooms API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $apiUrl
                ]);
                return null;
            }

            $data = $response->json();

            if (isset($data['success']) && $data['success'] === true && isset($data['rooms'])) {
                return $data['rooms'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('ExternalLocationService: Unexpected error fetching inactive rooms.', ['exception' => $e]);
            return null;
        }
    }

    /**
     * Fetch inactive room counts for all locations.
     *
     * @return array<string|int, int>|null
     */
    public function fetchInactiveRoomCounts(): ?array
    {
        $baseUrl = Config::get('services.external_locations_api.url');
        $apiToken = Config::get('services.external_locations_api.token');

        $apiUrl = dirname($baseUrl) . '/inactive-rooms-counts';

        if (empty($baseUrl) || empty($apiToken)) {
            Log::error('ExternalLocationService: API URL or Token not configured.');
            return null;
        }

        try {
            $response = Http::withToken($apiToken)->acceptJson()->get($apiUrl);

            if (! $response->successful()) {
                Log::error('ExternalLocationService: Inactive room counts API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $apiUrl
                ]);
                return null;
            }

            $data = $response->json();

            if (isset($data['success']) && $data['success'] === true && isset($data['counts'])) {
                return $data['counts'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('ExternalLocationService: Unexpected error fetching inactive room counts.', ['exception' => $e]);
            return null;
        }
    }

    /**
     * Upload a photo for a specific room to the external API.
     *
     * @param string|int $externalId
     * @param string $roomNumber
     * @param string $photoPath Full path to the photo file
     * @return bool
     */
    public function uploadRoomPhoto($externalId, string $roomNumber, string $photoPath): bool
    {
        $baseUrl = Config::get('services.external_locations_api.url');
        $apiToken = Config::get('services.external_locations_api.token');

        // URL: .../api/spaces/{id}/rooms/{room_number}/photos
        $apiUrl = dirname($baseUrl) . '/spaces/' . $externalId . '/rooms/' . urlencode($roomNumber) . '/photos';

        if (empty($baseUrl) || empty($apiToken)) {
            Log::error('ExternalLocationService: API URL or Token not configured.');
            return false;
        }

        if (!file_exists($photoPath)) {
            Log::error('ExternalLocationService: Photo file does not exist.', ['path' => $photoPath]);
            return false;
        }

        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->attach('photo', file_get_contents($photoPath), basename($photoPath))
                ->post($apiUrl);

            if (! $response->successful()) {
                Log::error('ExternalLocationService: Room photo upload failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $apiUrl,
                    'room' => $roomNumber
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('ExternalLocationService: Unexpected error uploading room photo.', [
                'exception' => $e->getMessage(),
                'room' => $roomNumber
            ]);
            return false;
        }
    }
}
