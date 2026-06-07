<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationCRUDTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
        $this->admin = User::factory()->create(['role' => Role::ADMIN->value]);
    }

    public function test_can_create_manual_location(): void
    {
        $locationData = [
            'name' => 'Manual Location',
            'address' => '123 Manual St',
            'postal_code' => '1234 AB',
            'city' => 'Manual City',
        ];

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('locations.store'), $locationData);

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('locations', [
            'name' => 'Manual Location',
            'external_id' => null,
        ]);
    }

    public function test_can_edit_manual_location(): void
    {
        $location = Location::factory()->create(['external_id' => null]);

        $response = $this->actingAs($this->admin)
            ->get(route('locations.edit', $location));

        $response->assertOk();
        $response->assertViewIs('locations.edit');
    }

    public function test_can_update_manual_location(): void
    {
        $location = Location::factory()->create(['external_id' => null, 'name' => 'Old Name']);

        $updateData = [
            'name' => 'New Name',
            'address' => 'Updated Address',
            'postal_code' => '5678 CD',
            'city' => 'Updated City',
        ];

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->put(route('locations.update', $location), $updateData);

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'New Name',
        ]);
    }

    public function test_can_delete_manual_location(): void
    {
        $location = Location::factory()->create(['external_id' => null]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->delete(route('locations.destroy', $location));

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('locations', ['id' => $location->id]);
    }

    public function test_cannot_edit_synced_location(): void
    {
        $location = Location::factory()->create(['external_id' => 123]);

        $response = $this->actingAs($this->admin)
            ->get(route('locations.edit', $location));

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('error', 'Gesynchroniseerde locaties kunnen niet worden gewijzigd.');
    }

    public function test_cannot_update_synced_location(): void
    {
        $location = Location::factory()->create(['external_id' => 123, 'name' => 'Synced Location']);

        $updateData = [
            'name' => 'Attempted Update',
            'address' => 'Some Address',
            'postal_code' => '1234 AB',
            'city' => 'Some City',
        ];

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->put(route('locations.update', $location), $updateData);

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('error', 'Gesynchroniseerde locaties kunnen niet worden gewijzigd.');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'Synced Location',
        ]);
    }

    public function test_cannot_delete_synced_location(): void
    {
        $location = Location::factory()->create(['external_id' => 123]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->delete(route('locations.destroy', $location));

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('error', 'Gesynchroniseerde locaties kunnen niet worden verwijderd.');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'deleted_at' => null,
        ]);
    }
}
