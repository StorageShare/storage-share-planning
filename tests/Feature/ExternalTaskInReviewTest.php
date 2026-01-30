<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ExternalTaskInReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.external_api.secret', 'test-secret');
    }

    public function test_external_task_is_created_with_in_review_status(): void
    {
        $location = Location::factory()->create();
        $payload = [
            'title' => 'External Task',
            'description' => 'Some description',
            'location_id' => $location->id,
            'priority' => 'normal',
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $response = $this->postJson('/api/v1/external/tasks', $payload, [
            'X-Api-Signature' => $signature,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tasks', [
            'title' => 'External Task',
            'status' => TaskStatus::IN_REVIEW->value,
        ]);
    }

    public function test_in_review_task_cannot_be_linked_to_planning(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $task = Task::factory()->create([
            'location_id' => $location->id,
            'status' => TaskStatus::IN_REVIEW,
        ]);

        $response = $this->actingAs($admin)->post('/plannings', [
            'location_ids' => [$location->id],
            'planned_date' => now()->addDay()->format('Y-m-d'),
            'vehicle_id' => $vehicle->id,
            'start_address_option' => 'Kantoor',
            'start_address' => 'Kantoor',
            'selected_backlog_tasks' => [$task->id],
        ]);

        $response->assertSessionHasErrors('selected_backlog_tasks.0');
    }

    public function test_admin_can_approve_in_review_task_to_open(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $location = Location::factory()->create();
        $task = Task::factory()->create([
            'location_id' => $location->id,
            'status' => TaskStatus::IN_REVIEW,
        ]);

        $response = $this->actingAs($admin)->post(route('tasks.approve', $task));

        $response->assertRedirect();
        $this->assertEquals(TaskStatus::OPEN, $task->fresh()->status);
    }
}
