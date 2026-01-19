<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => Role::ADMIN->value]);
        Config::set('services.external_locations_api.url', 'https://api.example.com/locations');
        Config::set('services.external_locations_api.token', 'test-token');
    }

    public function test_create_view_receives_external_locations(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'spaces' => [
                    ['id' => 1, 'name' => 'External Location 1'],
                    ['id' => 2, 'name' => 'External Location 2'],
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('locations.create'));

        $response->assertOk();
        $response->assertViewHas('externalLocations');
        $externalLocations = $response->viewData('externalLocations');
        $this->assertCount(2, $externalLocations);
        $this->assertEquals('External Location 1', $externalLocations[0]['name']);

        $response->assertSee('External Location 1 (ID: 1)');
        $response->assertSee('External Location 2 (ID: 2)');
    }

    public function test_edit_view_receives_external_locations(): void
    {
        $location = Location::factory()->create(['sync_external_id' => 1]);

        Http::fake([
            'api.example.com/*' => Http::response([
                'spaces' => [
                    ['id' => 1, 'name' => 'External Location 1'],
                    ['id' => 3, 'name' => 'External Location 3'],
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('locations.edit', $location));

        $response->assertOk();
        $response->assertViewHas('externalLocations');
        $response->assertSee('External Location 1 (ID: 1)');
        $response->assertSee('External Location 3 (ID: 3)');

        // Assert selected option
        $response->assertSee('value="1" selected', false);
    }
}
