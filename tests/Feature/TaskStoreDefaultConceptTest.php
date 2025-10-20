<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskStoreDefaultConceptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    public function test_customer_service_web_store_defaults_to_concept(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['role' => Role::CUSTOMER_SERVICE->value]);

        $payload = [
            'title' => 'Test taak',
            'description' => 'Beschrijving',
            'location_id' => $location->id,
        ];

        $response = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('locations.tasks.store', $location), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'location_id' => $location->id,
            'title' => 'Test taak',
            'status' => TaskStatus::CONCEPT->value,
        ]);
    }

    public function test_admin_web_store_defaults_to_open(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['role' => Role::ADMIN->value]);

        $payload = [
            'title' => 'Admin taak',
            'description' => 'Beschrijving',
            'location_id' => $location->id,
        ];

        $response = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('locations.tasks.store', $location), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'location_id' => $location->id,
            'title' => 'Admin taak',
            'status' => TaskStatus::OPEN->value,
        ]);
    }
}
