<?php

namespace Tests\Unit;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscalateTaskPrioritiesTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = Location::factory()->create([
            'name' => 'Test Locatie',
        ]);

        $this->user = User::factory()->create();
    }

    public function test_low_priority_task_escalates_to_normal_after_60_days()
    {
        // Create a low priority task that's 65 days old
        $task = Task::factory()->create([
            'location_id' => $this->location->id,
            'title' => 'Test Taak Laag',
            'description' => 'Test beschrijving',
            'priority' => TaskPriority::LOW,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subDays(65),
            'priority_updated_at' => Carbon::now()->subDays(65),
        ]);

        $this->artisan('tasks:escalate-priorities', ['--force' => true])
            ->expectsOutput('🚀 Starten met escalatie van taak prioriteiten...')
            ->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskPriority::NORMAL, $task->priority);
        $this->assertNotNull($task->priority_updated_at);
        $this->assertTrue($task->priority_updated_at->isToday());
    }

    public function test_normal_priority_task_escalates_to_high_after_30_days()
    {
        // Create a normal priority task that's 35 days old
        $task = Task::factory()->create([
            'location_id' => $this->location->id,
            'title' => 'Test Taak Normaal',
            'description' => 'Test beschrijving',
            'priority' => TaskPriority::NORMAL,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subDays(35),
            'priority_updated_at' => Carbon::now()->subDays(35),
        ]);

        $this->artisan('tasks:escalate-priorities', ['--force' => true])
            ->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskPriority::HIGH, $task->priority);
        $this->assertNotNull($task->priority_updated_at);
        $this->assertTrue($task->priority_updated_at->isToday());
    }

    public function test_low_priority_task_does_not_escalate_before_60_days()
    {
        // Create a low priority task that's only 50 days old
        $task = Task::factory()->create([
            'location_id' => $this->location->id,
            'title' => 'Test Taak Jong',
            'description' => 'Test beschrijving',
            'priority' => TaskPriority::LOW,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subDays(50),
            'priority_updated_at' => Carbon::now()->subDays(50),
        ]);

        $this->artisan('tasks:escalate-priorities', ['--force' => true])
            ->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskPriority::LOW, $task->priority);
    }

    public function test_normal_priority_task_does_not_escalate_before_30_days()
    {
        // Create a normal priority task that's only 20 days old
        $task = Task::factory()->create([
            'location_id' => $this->location->id,
            'title' => 'Test Taak Jong Normaal',
            'description' => 'Test beschrijving',
            'priority' => TaskPriority::NORMAL,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subDays(20),
            'priority_updated_at' => Carbon::now()->subDays(20),
        ]);

        $this->artisan('tasks:escalate-priorities', ['--force' => true])
            ->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskPriority::NORMAL, $task->priority);
    }

    public function test_high_priority_tasks_are_not_escalated()
    {
        // Create a high priority task that's 100 days old
        $task = Task::factory()->create([
            'location_id' => $this->location->id,
            'title' => 'Test Taak Hoog',
            'description' => 'Test beschrijving',
            'priority' => TaskPriority::HIGH,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subDays(100),
            'priority_updated_at' => Carbon::now()->subDays(100),
        ]);

        $this->artisan('tasks:escalate-priorities', ['--force' => true])
            ->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskPriority::HIGH, $task->priority);
    }

    public function test_completed_tasks_are_not_escalated()
    {
        // Create a completed low priority task that's 70 days old
        $task = Task::factory()->create([
            'location_id' => $this->location->id,
            'title' => 'Test Taak Voltooid',
            'description' => 'Test beschrijving',
            'priority' => TaskPriority::LOW,
            'status' => TaskStatus::COMPLETED,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subDays(70),
            'priority_updated_at' => Carbon::now()->subDays(70),
        ]);

        $this->artisan('tasks:escalate-priorities', ['--force' => true])
            ->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskPriority::LOW, $task->priority);
    }

    public function test_tasks_assigned_to_planning_are_not_escalated()
    {
        // Create a task that's assigned to a planning (not in backlog)
        $task = Task::factory()->create([
            'location_id' => $this->location->id,
            'title' => 'Test Taak In Planning',
            'description' => 'Test beschrijving',
            'priority' => TaskPriority::LOW,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subDays(70),
            'priority_updated_at' => Carbon::now()->subDays(70),
        ]);

        // Create a planning task to simulate the task being assigned to a planning
        $planning = Planning::factory()->create([
            'created_by' => $this->user->id,
        ]);

        PlanningTask::factory()->create([
            'planning_id' => $planning->id,
            'task_id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'location_id' => $task->location_id,
        ]);

        $this->artisan('tasks:escalate-priorities', ['--force' => true])
            ->assertExitCode(0);

        $task->refresh();
        $this->assertEquals(TaskPriority::LOW, $task->priority);
    }

    public function test_dry_run_does_not_change_tasks()
    {
        // Create a low priority task that's 65 days old
        $task = Task::factory()->create([
            'location_id' => $this->location->id,
            'title' => 'Test Taak Dry Run',
            'description' => 'Test beschrijving',
            'priority' => TaskPriority::LOW,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subDays(65),
            'priority_updated_at' => Carbon::now()->subDays(65),
        ]);

        $originalPriority = $task->priority;

        $this->artisan('tasks:escalate-priorities', ['--dry-run' => true])
            ->expectsOutput('🔍 Dry-run modus: geen wijzigingen doorgevoerd.')
            ->assertExitCode(0);

        $task->refresh();
        $this->assertEquals($originalPriority, $task->priority);
    }

    public function test_uses_priority_updated_at_when_available()
    {
        // Create a task that was created 100 days ago but priority was updated 20 days ago
        $task = Task::factory()->create([
            'location_id' => $this->location->id,
            'title' => 'Test Taak Recent Prioriteit Update',
            'description' => 'Test beschrijving',
            'priority' => TaskPriority::LOW,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subDays(100),
            'priority_updated_at' => Carbon::now()->subDays(20), // Priority was updated recently
        ]);

        $this->artisan('tasks:escalate-priorities', ['--force' => true])
            ->assertExitCode(0);

        $task->refresh();
        // Should still be LOW because priority was updated only 20 days ago (less than 60)
        $this->assertEquals(TaskPriority::LOW, $task->priority);
    }
}
