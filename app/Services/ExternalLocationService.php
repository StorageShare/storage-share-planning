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
}
