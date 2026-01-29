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
        ]);

        // 2. Mock API response
        $externalId = 123;
        Http::fake([
            'api.example.com/*' => Http::response([
                'spaces' => [
                    [
                        'id' => $externalId,
                        'name' => 'External Name',
                        'address' => 'Teststraat 1',
                        'postal_code' => '1234 AB',
                        'city' => 'Teststad',
                        'description' => 'Mooie locatie',
                        'lift' => 'Ja',
                        'total_rooms' => 18,
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
            'lift' => 'Ja',
            'total_rooms' => 18,
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
            'lift' => false,
        ]);

        // 2. Mock API response with different name and lift
        Http::fake([
            'api.example.com/*' => Http::response([
                'spaces' => [
                    [
                        'id' => $externalId,
                        'name' => 'External API Name',
                        'lift' => 'Ja',
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
            'lift' => 'Ja', // Updated
        ]);
    }

    public function test_it_updates_new_api_fields_for_linked_locations(): void
    {
        // 1. Create a location that was manual but is now linked via sync_external_id
        $externalId = 789;
        $linkedLocation = Location::factory()->create([
            'name' => 'Custom Name',
            'sync_external_id' => $externalId,
            'total_m2_net' => 100,
            'total_rooms' => 10,
        ]);

        // 2. Mock API response with new fields
        Http::fake([
            'api.example.com/*' => Http::response([
                'spaces' => [
                    [
                        'id' => $externalId,
                        'name' => 'External Name',
                        'total_m2_net' => 270,
                        'total_rooms' => 18,
                        'latitude' => '52.283409',
                        'longitude' => '4.866958',
                    ]
                ]
            ], 200),
        ]);

        // 3. Run sync
        $this->artisan('locations:sync')
            ->assertExitCode(0);

        // 4. Assert fields are updated
        $this->assertDatabaseHas('locations', [
            'id' => $linkedLocation->id,
            'name' => 'Custom Name', // Preserved
            'total_m2_net' => 270,
            'total_rooms' => 18,
            'latitude' => 52.283409,
            'longitude' => 4.866958,
        ]);
    }
}
