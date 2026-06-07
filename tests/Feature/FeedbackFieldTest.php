<?php

namespace Tests\Feature;

use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Planning;
use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackFieldTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => \App\Enums\Role::ADMIN]);
    }

    public function test_can_store_and_update_task_with_feedback_information(): void
    {
        $location = Location::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('locations.tasks.store', $location), [
            'title' => 'Test Taak',
            'description' => 'Test Omschrijving',
            'feedback_information' => 'Jaap',
            'location_id' => $location->id,
            'priority' => \App\Enums\TaskPriority::NORMAL->value,
            'created_by' => $this->admin->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Test Taak',
            'feedback_information' => 'Jaap',
        ]);

        $task = Task::first();
        $response = $this->actingAs($this->admin)->put(route('tasks.update', $task), [
            'title' => 'Updated Taak',
            'description' => 'Test Omschrijving',
            'feedback_information' => 'Kees',
            'status' => \App\Enums\TaskStatus::OPEN->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'feedback_information' => 'Kees',
        ]);
    }

    public function test_can_store_and_update_default_task_with_feedback_information(): void
    {
        $response = $this->actingAs($this->admin)->post(route('default-tasks.store'), [
            'title' => 'Default Taak',
            'description' => 'Test Omschrijving',
            'feedback_information' => 'Jaap',
            'time_calculation_type' => 'simplified',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('default_tasks', [
            'title' => 'Default Taak',
            'feedback_information' => 'Jaap',
        ]);

        $defaultTask = DefaultTask::where('title', 'Default Taak')->first();
        $response = $this->actingAs($this->admin)->put(route('default-tasks.update', $defaultTask), [
            'title' => 'Updated Default',
            'description' => 'Test Omschrijving',
            'feedback_information' => 'Kees',
            'time_calculation_type' => 'simplified',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('default_tasks', [
            'id' => $defaultTask->id,
            'feedback_information' => 'Kees',
        ]);
    }

    public function test_planning_copies_feedback_information_from_tasks(): void
    {
        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();

        $dt = DefaultTask::create(['title' => 'Default', 'description' => 'Desc', 'feedback_information' => 'Jaap']);
        $dt->locations()->sync([$location->id]);

        $t = Task::factory()->create(['location_id' => $location->id, 'feedback_information' => 'Kees']);

        $payload = [
            'planned_date' => now()->addDay()->format('Y-m-d'),
            'notes' => 'Note',
            'start_address_option' => 'Kantoor',
            'start_address' => 'Kantoor',
            'start_time' => '08:00',
            'vehicle_id' => $vehicle->id,
            'location_ids' => [$location->id],
            'location_order' => (string) $location->id,
            'user_ids' => [$user->id],
            'selected_default_tasks' => [$dt->id],
            'selected_backlog_tasks' => [$t->id],
        ];

        $response = $this->actingAs($this->admin)->post(route('plannings.store'), $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('planning_tasks', [
            'title' => 'Default',
            'feedback_information' => 'Jaap',
        ]);

        $this->assertDatabaseHas('planning_tasks', [
            'title' => $t->title,
            'feedback_information' => 'Kees',
        ]);
    }

    public function test_planning_show_displays_feedback_information(): void
    {
        $planning = Planning::factory()->create();
        $location = Location::factory()->create();
        $planning->locations()->attach($location->id, ['sort_order' => 0]);

        $t = Task::factory()->create(['location_id' => $location->id, 'feedback_information' => 'Kees']);

        \App\Models\PlanningTask::create([
            'planning_id' => $planning->id,
            'location_id' => $location->id,
            'task_id' => $t->id,
            'title' => 'Feedback Task',
            'description' => 'Desc',
            'feedback_information' => 'Jaap',
            'status' => \App\Enums\TaskStatus::OPEN,
        ]);

        $response = $this->actingAs($this->admin)->get(route('plannings.show', $planning));
        $response->assertStatus(200);
        $response->assertSee('Terugkoppeling informatie: Jaap');

        $response = $this->actingAs($this->admin)->get(route('tasks.show', $t));
        $response->assertStatus(200);
        $response->assertSee('Terugkoppeling informatie');
        $response->assertSee('Kees');
    }
}
