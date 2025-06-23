<?php

namespace App\Console\Commands;

use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncExternalLocationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize locations from the external API and soft delete those not present.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting locations synchronization...');
        Log::info('SyncExternalLocationsCommand: Starting synchronization.');

        $apiUrl = Config::get('services.external_locations_api.url');
        $apiToken = Config::get('services.external_locations_api.token');

        if (empty($apiUrl) || empty($apiToken)) {
            $this->error('API URL or Token is not configured.');
            Log::error('SyncExternalLocationsCommand: API URL or Token not configured.');

            return Command::FAILURE;
        }

        try {
            $response = Http::withToken($apiToken)->acceptJson()->get($apiUrl);

            if (! $response->successful()) {
                $this->error("API request failed: Status {$response->status()}");
                Log::error('SyncExternalLocationsCommand: API request failed.', ['status' => $response->status(), 'body' => $response->body()]);

                return Command::FAILURE;
            }

            $rawResponseData = $response->json();
            $externalLocationsData = null;

            // 1. Check for 'spaces' key (likely given API path /api/spaces)
            if (isset($rawResponseData['spaces']) && is_array($rawResponseData['spaces'])) {
                $externalLocationsData = $rawResponseData['spaces'];
            }
            // 2. Check for 'data' key (common Laravel convention)
            elseif (isset($rawResponseData['data']) && is_array($rawResponseData['data'])) {
                $externalLocationsData = $rawResponseData['data'];
            }
            // 3. Check if the raw response itself is the array of locations
            //    (i.e., response is `[{"id":1,...}, {"id":2,...}]`)
            //    Heuristic: is it an array, non-empty, and does its first item look like a location?
            elseif (is_array($rawResponseData) && ! empty($rawResponseData) && isset($rawResponseData[0]['id']) && isset($rawResponseData[0]['name'])) {
                $externalLocationsData = $rawResponseData;
            }
            // 4. Handle case where raw response is an empty array (valid scenario: no external locations)
            elseif (is_array($rawResponseData) && empty($rawResponseData)) {
                $externalLocationsData = $rawResponseData; // Should be an empty array []
            }

            // If $externalLocationsData is still not a valid array, then error out
            if (! is_array($externalLocationsData)) {
                $this->error('API response did not contain an identifiable array of locations (tried keys "spaces", "data", or root array).');
                Log::error('SyncExternalLocationsCommand: Failed to extract locations array from API response.', [
                    'response_body' => $response->body(), // Log the original body for debugging
                ]);

                return Command::FAILURE;
            }

            $externalLocationIds = Arr::pluck($externalLocationsData, 'id');
            // Check if IDs could be plucked, but only if the source array was not empty to begin with.
            // If $externalLocationsData is an empty array [], $externalLocationIds will also be [], which is valid.
            if (empty($externalLocationIds) && ! empty($externalLocationsData)) {
                Log::warning('SyncExternalLocationsCommand: API returned locations but failed to pluck IDs. Check structure of individual location items.', ['response_body' => $response->body(), 'parsed_locations_array' => $externalLocationsData]);
                $this->error('Could not parse IDs from API response items. Check log.');

                return Command::FAILURE;
            }

            // Soft delete local locations that have an external_id not in the API response
            $deletedNotInApiCount = Location::whereNotNull('external_id')
                ->whereNotIn('external_id', $externalLocationIds)
                ->delete(); // Soft delete
            if ($deletedNotInApiCount > 0) {
                $this->info("{$deletedNotInApiCount} local locations (with external_id) not found in API were soft-deleted.");
                Log::info("SyncExternalLocationsCommand: {$deletedNotInApiCount} local locations (with external_id) soft-deleted.");
            }

            // Soft delete local locations that were never synced (external_id is null)
            $deletedNeverSyncedCount = Location::whereNull('external_id')->delete(); // Soft delete
            if ($deletedNeverSyncedCount > 0) {
                $this->info("{$deletedNeverSyncedCount} local unsynced locations (external_id is null) were soft-deleted.");
                Log::info("SyncExternalLocationsCommand: {$deletedNeverSyncedCount} local unsynced locations soft-deleted.");
            }

            $createdCount = 0;
            $updatedCount = 0;
            $restoredCount = 0;

            foreach ($externalLocationsData as $extLocation) {
                if (! isset($extLocation['id']) || ! isset($extLocation['name'])) {
                    Log::warning('SyncExternalLocationsCommand: Skipping external location due to missing id or name.', ['location_data' => $extLocation]);

                    continue;
                }

                // Use withTrashed to find even soft-deleted records for update
                $location = Location::withTrashed()->updateOrCreate(
                    ['external_id' => $extLocation['id']],
                    [
                        'name' => $extLocation['name'],
                        'outdoor_safe_code' => $extLocation['outdoor_safe_code'] ?? null,
                        'indoor_safe_code' => $extLocation['indoor_safe_code'] ?? null,
                        'outdoor_safe_content' => $extLocation['outdoor_safe_content'] ?? null,
                        'indoor_safe_content' => $extLocation['indoor_safe_content'] ?? null,
                        'intratone_number' => $extLocation['intratone_number'] ?? null,
                        'intratone_multiple_numbers' => $extLocation['intratone_multiple_numbers'] ?? null,
                        'gate_number' => $extLocation['gate_number'] ?? null,
                        'bv' => $extLocation['bv'] ?? null,
                        'last_synced_at' => Carbon::now(),
                        'deleted_at' => null, // Explicitly restore if found (or ensure it's null for new/updated)
                    ]
                );

                if ($location->wasRecentlyCreated) {
                    $createdCount++;
                } else {
                    // Check if it was restored
                    if ($location->trashed() && ! $location->wasChanged('deleted_at')) {
                        // This case is tricky. updateOrCreate on a trashed model might not trigger wasChanged() for deleted_at if it was already null from restore.
                        // If it was trashed and now deleted_at is null, it's restored.
                        // A more reliable way to check for restoration is if it was trashed before the operation.
                        // However, updateOrCreate doesn't give us the previous state directly.
                        // For simplicity, we assume if it wasn't created and `deleted_at` is now null, it was updated/restored.
                        // A better check for restored: $location->wasChanged('deleted_at') && is_null($location->deleted_at)
                        // But if it was already restored (deleted_at was null), this won't trigger.
                        // Let's assume wasChanged() covers name/last_synced_at changes for existing non-deleted.
                        // If it was soft-deleted and is now found by updateOrCreate, setting deleted_at = null restores it.
                        // The $location->trashed() check *before* setting deleted_at = null would be ideal.
                    }
                    if ($location->wasChanged()) {
                        // This will be true if name, last_synced_at or deleted_at (from null to value or value to null) changed.
                        // If deleted_at was changed from a timestamp to null, it means it was restored.
                        if ($location->wasChanged('deleted_at') && is_null($location->deleted_at)) {
                            $restoredCount++;
                        } else {
                            $updatedCount++; // counts other changes if not restored in this cycle
                        }
                    }
                }
            }

            $this->info("Synchronization complete. Created: {$createdCount}, Updated: {$updatedCount}, Restored: {$restoredCount}.");
            Log::info("SyncExternalLocationsCommand: Sync complete. C:{$createdCount}, U:{$updatedCount}, R:{$restoredCount}.");

            return Command::SUCCESS;

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $this->error("API Request Exception: {$e->getMessage()}");
            Log::error('SyncExternalLocationsCommand: API Request Exception.', ['exception' => $e]);

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("An unexpected error occurred: {$e->getMessage()}");
            Log::error('SyncExternalLocationsCommand: Unexpected error.', ['exception' => $e]);

            return Command::FAILURE;
        }
    }
}
