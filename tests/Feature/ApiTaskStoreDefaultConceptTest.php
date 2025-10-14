<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTaskStoreDefaultConceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_store_defaults_to_concept_for_customer_service(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['role' => Role::CUSTOMER_SERVICE->value]);

        $payload = [
            'title' => 'API taak',
            'description' => 'Beschrijving',
            'location_id' => $location->id,
        ];

        $response = $this->actingAs($user)->postJson("/api/v1/locations/{$location->id}/tasks", $payload);
        $response->assertCreated();
        $response->assertJsonPath('data.status', TaskStatus::CONCEPT->value);

        $this->assertDatabaseHas('tasks', [
            'location_id' => $location->id,
            'title' => 'API taak',
            'status' => TaskStatus::CONCEPT->value,
        ]);
    }
}
