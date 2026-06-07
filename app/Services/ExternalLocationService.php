<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ExternalLocationService
{
    public function __construct(
        private readonly StorageShareApiService $storageShareApi,
    ) {}

    /**
     * Fetch external locations from the API.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function fetchExternalLocations(): ?array
    {
        if (! $this->storageShareApi->isConfigured()) {
            Log::error('ExternalLocationService: API URL or Token not configured.');

            return null;
        }

        try {
            $response = $this->storageShareApi->getSpaces();

            if (! $response->successful()) {
                Log::error('ExternalLocationService: API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $rawResponseData = $response->json();

            if (isset($rawResponseData['spaces']) && is_array($rawResponseData['spaces'])) {
                return $rawResponseData['spaces'];
            }

            if (isset($rawResponseData['data']) && is_array($rawResponseData['data'])) {
                return $rawResponseData['data'];
            }

            if (is_array($rawResponseData) && ! empty($rawResponseData) && isset($rawResponseData[0]['id']) && isset($rawResponseData[0]['name'])) {
                return $rawResponseData;
            }

            if (is_array($rawResponseData) && empty($rawResponseData)) {
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
     * @param  string|int  $externalId
     * @return array<int, array{name: string, description?: ?string, group_name?: ?string}>|null
     */
    public function fetchInactiveRooms($externalId): ?array
    {
        if (! $this->storageShareApi->isConfigured()) {
            Log::error('ExternalLocationService: API URL or Token not configured.');

            return null;
        }

        try {
            $response = $this->storageShareApi->get('/spaces/'.$externalId.'/inactive-rooms');

            if (! $response->successful()) {
                Log::error('ExternalLocationService: Inactive rooms API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'external_id' => $externalId,
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
        if (! $this->storageShareApi->isConfigured()) {
            Log::error('ExternalLocationService: API URL or Token not configured.');

            return null;
        }

        try {
            $response = $this->storageShareApi->get('/inactive-rooms-counts');

            if (! $response->successful()) {
                Log::error('ExternalLocationService: Inactive room counts API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
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
     * @param  string|int  $externalId
     * @param  string  $photoPath  Full path to the photo file
     */
    public function uploadRoomPhoto($externalId, string $roomNumber, string $photoPath): bool
    {
        if (! $this->storageShareApi->isConfigured()) {
            Log::error('ExternalLocationService: API URL or Token not configured.');

            return false;
        }

        if (! file_exists($photoPath)) {
            Log::error('ExternalLocationService: Photo file does not exist.', ['path' => $photoPath]);

            return false;
        }

        try {
            $response = $this->storageShareApi->postFile(
                '/spaces/'.$externalId.'/rooms/'.urlencode($roomNumber).'/photos',
                'photo',
                $photoPath,
                basename($photoPath),
            );

            if (! $response->successful()) {
                Log::error('ExternalLocationService: Room photo upload failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'room' => $roomNumber,
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('ExternalLocationService: Unexpected error uploading room photo.', [
                'exception' => $e->getMessage(),
                'room' => $roomNumber,
            ]);

            return false;
        }
    }
}
