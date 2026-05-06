<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
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

    public function test_update_removes_stale_backlog_task_selection_when_location_is_removed(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $keptLocation = Location::factory()->create();
        $removedLocation = Location::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $planning = Planning::factory()->create([
            'planned_date' => now()->toDateString(),
            'status' => 'in_progress',
            'vehicle_id' => $vehicle->id,
        ]);
        $planning->locations()->attach([
            $keptLocation->id => ['sort_order' => 0],
            $removedLocation->id => ['sort_order' => 1],
        ]);

        $staleTask = Task::factory()->completed()->create(['location_id' => $removedLocation->id]);
        $stalePlanningTask = PlanningTask::factory()->create([
            'planning_id' => $planning->id,
            'task_id' => $staleTask->id,
            'location_id' => $removedLocation->id,
            'title' => $staleTask->title,
            'status' => TaskStatus::COMPLETED,
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->put(route('plannings.update', $planning), [
                'location_ids' => [$keptLocation->id],
                'location_order' => (string) $keptLocation->id,
                'planned_date' => $planning->planned_date->toDateString(),
                'notes' => $planning->notes,
                'start_address_option' => 'Anders',
                'start_address_custom' => 'Teststraat 1, 1234 AB Teststad',
                'start_time' => '08:00',
                'vehicle_id' => $vehicle->id,
                'selected_backlog_tasks' => [$staleTask->id],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('plannings.show', $planning));
        $this->assertDatabaseMissing('planning_tasks', ['id' => $stalePlanningTask->id]);
        $this->assertDatabaseHas('location_planning', [
            'planning_id' => $planning->id,
            'location_id' => $keptLocation->id,
        ]);
        $this->assertDatabaseMissing('location_planning', [
            'planning_id' => $planning->id,
            'location_id' => $removedLocation->id,
        ]);
    }
}
