<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningCompleteReleasesBacklogTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_uncompleted_backlog_tasks_are_released_on_planning_completion(): void
    {
        // Admin to perform actions
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);

        $planning = Planning::factory()->create(['status' => 'open']);
        $location = Location::factory()->create();

        // Create a backlog task and link it to the planning as a planning_task marked as skipped
        $task = Task::factory()->create([
            'location_id' => $location->id,
            'status' => TaskStatus::SKIPPED->value,
        ]);

        $pt = PlanningTask::create([
            'planning_id' => $planning->id,
            'task_id' => $task->id,
            'location_id' => $location->id,
            'title' => $task->title,
            'description' => $task->description ?? '',
            'status' => TaskStatus::SKIPPED->value,
        ]);

        // Complete the planning
        $response = $this->actingAs($admin)
            ->post(route('plannings.complete', $planning));

        $response->assertRedirect();

        // The planning should be completed
        $planning->refresh();
        $this->assertEquals('completed', $planning->status);

        // The planning_task should be removed
        $this->assertDatabaseMissing('planning_tasks', ['id' => $pt->id]);

        // The original backlog task should be reset to OPEN so it appears again in backlog
        $task->refresh();
        $this->assertEquals(TaskStatus::OPEN, $task->status);
    }
}
