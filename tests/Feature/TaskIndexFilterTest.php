<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_service_only_sees_concept_tasks_in_location_task_index(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['role' => Role::CUSTOMER_SERVICE->value]);

        $conceptTask = Task::factory()->concept()->create(['location_id' => $location->id]);
        $openTask = Task::factory()->create(['location_id' => $location->id, 'status' => 'open']);

        $response = $this->actingAs($user)->get(route('locations.tasks.index', $location));
        $response->assertOk();

        $response->assertSee($conceptTask->title);
        $response->assertDontSee($openTask->title);
    }

    public function test_admin_sees_all_tasks_in_location_task_index(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['role' => Role::ADMIN->value]);

        $conceptTask = Task::factory()->concept()->create(['location_id' => $location->id]);
        $openTask = Task::factory()->create(['location_id' => $location->id, 'status' => 'open']);

        $response = $this->actingAs($user)->get(route('locations.tasks.index', $location));
        $response->assertOk();

        $response->assertSee($conceptTask->title);
        $response->assertSee($openTask->title);
    }
}
