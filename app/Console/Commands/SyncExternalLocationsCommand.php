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
    public function handle(\App\Services\ExternalLocationService $externalLocationService): int
    {
        $this->info('Starting locations synchronization...');
        Log::info('SyncExternalLocationsCommand: Starting synchronization.');

        $externalLocationsData = $externalLocationService->fetchExternalLocations();

        if ($externalLocationsData === null) {
            $this->error('Failed to fetch external locations.');

            return Command::FAILURE;
        }

        try {
            $externalLocationIds = Arr::pluck($externalLocationsData, 'id');
            // Check if IDs could be plucked, but only if the source array was not empty to begin with.
            // If $externalLocationsData is an empty array [], $externalLocationIds will also be [], which is valid.
            if (empty($externalLocationIds) && ! empty($externalLocationsData)) {
                Log::warning('SyncExternalLocationsCommand: API returned locations but failed to pluck IDs. Check structure of individual location items.', ['parsed_locations_array' => $externalLocationsData]);
                $this->error('Could not parse IDs from API response items. Check log.');

                return Command::FAILURE;
            }

            // Soft delete local locations that have an external_id or sync_external_id not in the API response
            $deletedNotInApiCount = Location::where(function ($query) use ($externalLocationIds) {
                    $query->whereNotNull('external_id')
                          ->whereNotIn('external_id', $externalLocationIds);
                })
                ->orWhere(function ($query) use ($externalLocationIds) {
                    $query->whereNotNull('sync_external_id')
                          ->whereNotIn('sync_external_id', $externalLocationIds);
                })
                ->delete(); // Soft delete
            if ($deletedNotInApiCount > 0) {
                $this->info("{$deletedNotInApiCount} local locations (with external_id or sync_external_id) not found in API were soft-deleted.");
                Log::info("SyncExternalLocationsCommand: {$deletedNotInApiCount} local locations (with external_id or sync_external_id) soft-deleted.");
            }

            $createdCount = 0;
            $updatedCount = 0;
            $restoredCount = 0;

            foreach ($externalLocationsData as $extLocation) {
                if (! isset($extLocation['id']) || ! isset($extLocation['name'])) {
                    Log::warning('SyncExternalLocationsCommand: Skipping external location due to missing id or name.', ['location_data' => $extLocation]);

                    continue;
                }

                // Check if we already have this location by external_id or sync_external_id
                $location = Location::withTrashed()
                    ->where(function ($query) use ($extLocation) {
                        $query->where('external_id', $extLocation['id'])
                              ->orWhere('sync_external_id', $extLocation['id']);
                    })
                    ->first();

                $dataToUpdate = [
                    'address' => $extLocation['address'] ?? null,
                    'postal_code' => $extLocation['postal_code'] ?? null,
                    'city' => $extLocation['city'] ?? null,
                    'outdoor_safe_code' => $extLocation['outdoor_safe_code'] ?? null,
                    'indoor_safe_code' => $extLocation['indoor_safe_code'] ?? null,
                    'outdoor_safe_content' => $extLocation['outdoor_safe_content'] ?? null,
                    'indoor_safe_content' => $extLocation['indoor_safe_content'] ?? null,
                    'intratone_number' => $extLocation['intratone_number'] ?? null,
                    'intratone_multiple_numbers' => $extLocation['intratone_multiple_numbers'] ?? null,
                    'gate_number' => $extLocation['gate_number'] ?? null,
                    'lift' => $extLocation['lift'] ?? null,
                    'type_deur' => $extLocation['type_deur'] ?? null,
                    'total_m2_net' => $extLocation['total_m2_net'] ?? null,
                    'total_m2_gross' => $extLocation['total_m2_gross'] ?? null,
                    'total_rooms' => $extLocation['total_rooms'] ?? null,
                    'bv' => $extLocation['bv'] ?? null,
                    'last_synced_at' => Carbon::now(),
                    'deleted_at' => null, // Restore if found
                ];

                if (! $location) {
                    // If not found by external_id, it might have been manually created and now linked.
                    // But wait, if it was manually created and linked, it WOULD have an external_id now.
                    // If it's the first time we see this external_id, we create it.
                    $dataToUpdate['external_id'] = $extLocation['id'];
                    $dataToUpdate['name'] = $extLocation['name'];
                    $location = Location::create($dataToUpdate);
                    $createdCount++;
                } else {
                    // It exists. We update everything EXCEPT the name.
                    $location->fill($dataToUpdate);

                    if ($location->trashed() && is_null($location->deleted_at)) {
                        $restoredCount++;
                    } elseif ($location->isDirty()) {
                        $updatedCount++;
                    }

                    $location->save();
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
