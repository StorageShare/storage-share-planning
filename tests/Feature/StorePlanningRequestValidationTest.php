<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorePlanningRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    private function validPayload(array $overrides = []): array
    {
        $location = $overrides['__location'] ?? Location::factory()->create();
        $vehicle = $overrides['__vehicle'] ?? Vehicle::factory()->create();

        return array_merge([
            'location_ids' => [$location->id],
            'planned_date' => now()->toDateString(),
            'notes' => null,
            'start_address_option' => 'Anders',
            'start_address_custom' => 'Teststraat 1, 1234 AB Teststad',
            'start_address' => 'irrelevant because of prepareForValidation',
            'start_time' => '08:00',
            'vehicle_id' => $vehicle->id,
        ], $overrides);
    }

    public function test_cannot_select_concept_task_for_planning(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $location = Location::factory()->create();
        $conceptTask = Task::factory()->concept()->create(['location_id' => $location->id]);

        $payload = $this->validPayload(['__location' => $location, 'selected_backlog_tasks' => [$conceptTask->id]]);

        $response = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.store'), $payload);

        $response->assertSessionHasErrors(['selected_backlog_tasks.0']);
    }

    public function test_can_select_open_or_in_progress_tasks_for_planning(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $location = Location::factory()->create();
        $openTask = Task::factory()->create(['location_id' => $location->id, 'status' => TaskStatus::OPEN->value]);
        $inProgressTask = Task::factory()->inProgress()->create(['location_id' => $location->id]);

        $payload = $this->validPayload(['__location' => $location, 'selected_backlog_tasks' => [$openTask->id, $inProgressTask->id]]);

        $response = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('plannings.index'));
    }
}
