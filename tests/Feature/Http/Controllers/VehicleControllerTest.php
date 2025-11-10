<?php

namespace Feature\Http\Controllers;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ------------- Page rendering

    public function test_index_displays_list_for_admin(): void
    {
        $this->actingAsAdmin();

        Vehicle::factory()->count(3)->create();

        $res = $this->get(route('vehicles.index'));
        $res->assertOk();
        $res->assertSee('Voertuigen');
    }

    public function test_create_page_renders_with_types_for_admin(): void
    {
        $this->actingAsAdmin();

        $res = $this->get(route('vehicles.create'));
        $res->assertOk();
        // Ensure the select options labels are present
        $res->assertSee('Auto');
        $res->assertSee('Bus');
    }

    public function test_edit_page_renders_for_admin(): void
    {
        $this->actingAsAdmin();
        $vehicle = Vehicle::factory()->create();

        $res = $this->get(route('vehicles.edit', $vehicle));
        $res->assertOk();
        $res->assertSee(e($vehicle->name));
        $res->assertSee(e($vehicle->license_number));
    }

    // ------------- Store

    public function test_store_normalizes_license_and_succeeds(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'name' => 'Test Wagen',
            'license_number' => 'l-800-jn',
            'type' => VehicleType::CAR->value,
        ];

        $res = $this->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('vehicles.store'), $payload);

        $res->assertRedirect(route('vehicles.index'));
        $res->assertSessionHas('status', 'Voertuig succesvol aangemaakt.');

        $this->assertDatabaseHas('vehicles', [
            'name' => 'Test Wagen',
            'license_number' => 'L800JN', // normalized
            'type' => VehicleType::CAR->value,
        ]);
    }

    public function test_store_rejects_duplicate_normalized_plate_and_preserves_input(): void
    {
        $this->actingAsAdmin();

        Vehicle::factory()->create([
            'license_number' => 'L800JN',
        ]);

        $payload = [
            'name' => 'Another',
            'license_number' => 'l-800-jn', // same normalized value
            'type' => VehicleType::BUS->value,
        ];

        $res = $this->from(route('vehicles.create'))
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('vehicles.store'), $payload);

        $res->assertRedirect(route('vehicles.create'));
        $res->assertSessionHasErrors(['license_number']);
        $res->assertSessionHasInput($payload);
    }

    // ------------- Update

    public function test_update_changes_data_and_normalizes_plate(): void
    {
        $this->actingAsAdmin();
        $vehicle = Vehicle::factory()->create([
            'name' => 'Old',
            'license_number' => 'AB123C',
            'type' => VehicleType::BUS->value,
        ]);

        $payload = [
            'name' => 'New Name',
            'license_number' => 'a-b-1-2-3-c', // -> AB123C
            'type' => VehicleType::CAR->value,
        ];

        $res = $this->withHeader('X-CSRF-TOKEN', $this->token)
            ->put(route('vehicles.update', $vehicle), $payload);

        $res->assertRedirect(route('vehicles.index'));
        $res->assertSessionHas('status', 'Voertuig succesvol bijgewerkt.');

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'name' => 'New Name',
            'license_number' => 'AB123C',
            'type' => VehicleType::CAR->value,
        ]);
    }

    public function test_update_rejects_plate_that_conflicts_with_another_record(): void
    {
        $this->actingAsAdmin();
        $v1 = Vehicle::factory()->create(['license_number' => 'L800JN']);
        $v2 = Vehicle::factory()->create(['license_number' => 'ZZ999Z']);

        // Trying to update v2 to conflicting plate of v1
        $payload = [
            'name' => $v2->name,
            'license_number' => 'l-800-jn', // normalized to L800JN
            'type' => $v2->type instanceof VehicleType ? $v2->type->value : $v2->type,
        ];

        $res = $this->from(route('vehicles.edit', $v2))
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->put(route('vehicles.update', $v2), $payload);

        $res->assertRedirect(route('vehicles.edit', $v2));
        $res->assertSessionHasErrors(['license_number']);
        $res->assertSessionHasInput($payload);

        // Ensure DB unchanged for v2
        $this->assertDatabaseHas('vehicles', [
            'id' => $v2->id,
            'license_number' => 'ZZ999Z',
        ]);
    }

    // ------------- Destroy

    public function test_destroy_deletes_vehicle(): void
    {
        $this->actingAsAdmin();
        $vehicle = Vehicle::factory()->create();

        $res = $this->withHeader('X-CSRF-TOKEN', $this->token)
            ->delete(route('vehicles.destroy', $vehicle));
        $res->assertRedirect(route('vehicles.index'));
        $res->assertSessionHas('status', 'Voertuig succesvol verwijderd.');

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    // ------------- Validation basics

    public function test_store_requires_required_fields_and_valid_enum(): void
    {
        $this->actingAsAdmin();

        $res = $this->from(route('vehicles.create'))
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('vehicles.store'), [
                // empty payload
            ]);

        $res->assertRedirect(route('vehicles.create'));
        $res->assertSessionHasErrors(['name', 'license_number', 'type']);
    }

    public function test_store_rejects_invalid_enum_value(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'name' => 'X',
            'license_number' => 'abc123', // will be normalized
            'type' => 'plane', // invalid
        ];

        $res = $this->from(route('vehicles.create'))
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('vehicles.store'), $payload);

        $res->assertRedirect(route('vehicles.create'));
        $res->assertSessionHasErrors(['type']);
    }

    // ------------- Access control

    public function test_guest_is_redirected_to_login(): void
    {
        $res = $this->get(route('vehicles.index'));
        $res->assertRedirect('/login');
    }

    public function test_non_admin_gets_403_on_vehicles_routes(): void
    {
        $user = User::factory()->gebruiker()->create();
        $this->actingAs($user);

        $res = $this->get(route('vehicles.index'));
        $res->assertStatus(403);
    }
}
