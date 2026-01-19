<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncExternalLocationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.external_locations_api.url', 'https://api.example.com/locations');
        Config::set('services.external_locations_api.token', 'test-token');
    }

    public function test_it_syncs_locations_and_preserves_manually_created_ones(): void
    {
        // 1. Create a manually created location
        $manualLocation = Location::factory()->create([
            'name' => 'Manual Location',
            'external_id' => null,
            'address' => 'Old Address',
        ]);

        // 2. Mock API response
        $externalId = 123;
        Http::fake([
            'api.example.com/*' => Http::response([
                'spaces' => [
                    [
                        'id' => $externalId,
                        'name' => 'External Name',
                        'outdoor_safe_code' => 'NEW_CODE',
                        'address' => 'External Address', // This field is NOT in our update list in the command, wait.
                    ]
                ]
            ], 200),
        ]);

        // 3. Run sync
        $this->artisan('locations:sync')
            ->assertExitCode(0);

        // 4. Assert manual location still exists
        $this->assertDatabaseHas('locations', [
            'id' => $manualLocation->id,
            'external_id' => null,
            'name' => 'Manual Location',
        ]);

        // 5. Assert external location was created
        $this->assertDatabaseHas('locations', [
            'external_id' => $externalId,
            'name' => 'External Name',
            'outdoor_safe_code' => 'NEW_CODE',
        ]);
    }

    public function test_it_updates_linked_location_but_preserves_name(): void
    {
        // 1. Create a location that was manual but is now linked via sync_external_id
        $externalId = 456;
        $linkedLocation = Location::factory()->create([
            'name' => 'My Custom Name',
            'sync_external_id' => $externalId,
            'external_id' => null,
            'outdoor_safe_code' => 'OLD_CODE',
        ]);

        // 2. Mock API response with different name and code
        Http::fake([
            'api.example.com/*' => Http::response([
                'spaces' => [
                    [
                        'id' => $externalId,
                        'name' => 'External API Name',
                        'address' => 'External Address',
                        'outdoor_safe_code' => 'NEW_CODE',
                    ]
                ]
            ], 200),
        ]);

        // 3. Run sync
        $this->artisan('locations:sync')
            ->assertExitCode(0);

        // 4. Assert name is preserved, but other fields updated
        $this->assertDatabaseHas('locations', [
            'id' => $linkedLocation->id,
            'sync_external_id' => $externalId,
            'name' => 'My Custom Name', // Preserved
            'outdoor_safe_code' => 'NEW_CODE', // Updated
            'address' => 'External Address', // Should be updated
        ]);
    }

    public function test_it_updates_address_fields_for_linked_locations(): void
    {
        // 1. Create a location that was manual but is now linked via sync_external_id
        $externalId = 789;
        $linkedLocation = Location::factory()->create([
            'name' => 'Custom Name',
            'sync_external_id' => $externalId,
            'address' => 'Old Address',
            'postal_code' => '1111 AA',
            'city' => 'Old City',
        ]);

        // 2. Mock API response with new address fields
        Http::fake([
            'api.example.com/*' => Http::response([
                'spaces' => [
                    [
                        'id' => $externalId,
                        'name' => 'External Name',
                        'address' => 'New Address',
                        'postal_code' => '2222 BB',
                        'city' => 'New City',
                        'outdoor_safe_code' => 'NEW_CODE',
                    ]
                ]
            ], 200),
        ]);

        // 3. Run sync
        $this->artisan('locations:sync')
            ->assertExitCode(0);

        // 4. Assert address fields are updated
        $this->assertDatabaseHas('locations', [
            'id' => $linkedLocation->id,
            'name' => 'Custom Name', // Preserved
            'address' => 'New Address',
            'postal_code' => '2222 BB',
            'city' => 'New City',
            'outdoor_safe_code' => 'NEW_CODE',
        ]);
    }
}
