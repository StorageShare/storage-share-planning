<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningCompletionTaskCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => Role::ADMIN->value]);
    }

    /** @test */
    public function it_removes_uncompleted_default_tasks_when_planning_is_completed()
    {
        // 1. Setup data
        $location = Location::factory()->create();
        $planning = Planning::factory()->create(['status' => 'open']);

        $defaultTask = DefaultTask::create([
            'title' => 'Standaard Taak',
            'description' => 'Test',
            'applies_to_all_locations' => true
        ]);

        // 2. Create tasks like PlanningController::createPlanningTasks does
        $task = Task::create([
            'location_id' => $location->id,
            'title' => $defaultTask->title,
            'description' => $defaultTask->description,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->admin->id,
        ]);

        $planningTask = $planning->planningTasks()->create([
            'location_id' => $location->id,
            'task_id' => $task->id,
            'default_task_id' => $defaultTask->id,
            'title' => $defaultTask->title,
            'description' => $defaultTask->description,
            'status' => TaskStatus::OPEN,
        ]);

        // 3. Create another task that IS completed
        $completedTask = Task::create([
            'location_id' => $location->id,
            'title' => 'Voltooide Taak',
            'description' => 'Test',
            'status' => TaskStatus::COMPLETED,
            'created_by' => $this->admin->id,
        ]);

        $completedPlanningTask = $planning->planningTasks()->create([
            'location_id' => $location->id,
            'task_id' => $completedTask->id,
            'default_task_id' => $defaultTask->id,
            'title' => $defaultTask->title,
            'description' => $defaultTask->description,
            'status' => TaskStatus::COMPLETED,
        ]);

        // 4. Create a backlog task (no default_task_id) that is NOT completed
        $backlogTask = Task::create([
            'location_id' => $location->id,
            'title' => 'Backlog Taak',
            'description' => 'Test',
            'status' => TaskStatus::OPEN,
            'created_by' => $this->admin->id,
        ]);

        $backlogPlanningTask = $planning->planningTasks()->create([
            'location_id' => $location->id,
            'task_id' => $backlogTask->id,
            'title' => $backlogTask->title,
            'description' => $backlogTask->description,
            'status' => TaskStatus::OPEN,
        ]);

        $this->assertCount(3, Task::all());
        $this->assertCount(3, PlanningTask::all());

        // 5. Complete the planning
        $response = $this->actingAs($this->admin)->post(route('plannings.complete', $planning));
        $response->assertRedirect();

        // 6. Assertions
        // The uncompleted default task and its planning task should be removed
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('planning_tasks', ['id' => $planningTask->id]);

        // The completed default task should stay
        $this->assertDatabaseHas('tasks', ['id' => $completedTask->id]);
        $this->assertDatabaseHas('planning_tasks', ['id' => $completedPlanningTask->id]);

        // The uncompleted backlog task should stay (as it's not from a default task)
        $this->assertDatabaseHas('tasks', ['id' => $backlogTask->id]);
        $this->assertDatabaseHas('planning_tasks', ['id' => $backlogPlanningTask->id]);

        $this->assertCount(2, Task::all());
        $this->assertCount(2, PlanningTask::all());
    }
}
