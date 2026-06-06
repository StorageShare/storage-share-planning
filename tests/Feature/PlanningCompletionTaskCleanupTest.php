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
        $planning->locations()->attach($location->id); // BELANGRIJK: locatie koppelen aan planning

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

        // Exclude the auto-generated tasks the LocationObserver creates for every new location.
        $autoTitles = ['Schoonmaken', 'Controleronde'];
        $this->assertCount(3, Task::whereNotIn('title', $autoTitles)->get());
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
        // Note: in PlanningController@complete, uncompleted backlog planning tasks are DELETED
        // but the underlying Task status is set back to OPEN.
        $this->assertDatabaseMissing('planning_tasks', ['id' => $backlogPlanningTask->id]);
        $this->assertEquals(TaskStatus::OPEN, $backlogTask->fresh()->status);

        $this->assertCount(2, Task::whereNotIn('title', $autoTitles)->get());
        $this->assertCount(1, PlanningTask::all());
    }

    /** @test */
    public function it_removes_floating_uncompleted_default_tasks_in_backlog_for_locations_in_planning()
    {
        $location = Location::factory()->create();
        $otherLocation = Location::factory()->create();
        $planning = Planning::factory()->create(['status' => 'open']);
        $planning->locations()->attach($location->id);

        $defaultTask = DefaultTask::create([
            'title' => 'Standaard Taak',
            'description' => 'Test',
            'applies_to_all_locations' => true
        ]);

        // Task gekoppeld aan planning (wordt al opgeruimd)
        $linkedTask = Task::create([
            'location_id' => $location->id,
            'title' => $defaultTask->title,
            'description' => $defaultTask->description,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->admin->id,
        ]);
        $planning->planningTasks()->create([
            'location_id' => $location->id,
            'task_id' => $linkedTask->id,
            'default_task_id' => $defaultTask->id,
            'title' => $defaultTask->title,
            'description' => $defaultTask->description,
            'status' => TaskStatus::OPEN,
        ]);

        // 'Zwevende' standaardtaak in backlog voor dezelfde locatie (zou ook opgeruimd moeten worden)
        $floatingTask = Task::create([
            'location_id' => $location->id,
            'title' => $defaultTask->title,
            'description' => $defaultTask->description,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->admin->id,
        ]);
        // We simuleren dat dit een standaardtaak is door titel en locatie match,
        // maar in de echte database hebben we geen directe link van Task naar DefaultTask
        // Behalve via de planning_tasks tabel of we moeten matchen op titel/locatie.

        // Zwevende standaardtaak voor een ANDERE locatie (mag NIET opgeruimd worden)
        $otherLocationTask = Task::create([
            'location_id' => $otherLocation->id,
            'title' => $defaultTask->title,
            'description' => $defaultTask->description,
            'status' => TaskStatus::OPEN,
            'created_by' => $this->admin->id,
        ]);

        // Exclude the auto-generated tasks the LocationObserver creates for every new location.
        $autoTitles = ['Schoonmaken', 'Controleronde'];
        $this->assertCount(3, Task::whereNotIn('title', $autoTitles)->get());

        // Complete planning
        $this->actingAs($this->admin)->post(route('plannings.complete', $planning));

        // Linked task moet weg zijn
        $this->assertDatabaseMissing('tasks', ['id' => $linkedTask->id]);

        // Floating task voor dezelfde locatie zou ook weg moeten zijn volgens de wens van de gebruiker
        $this->assertDatabaseMissing('tasks', ['id' => $floatingTask->id]);

        // Floating task voor andere locatie moet blijven
        $this->assertDatabaseHas('tasks', ['id' => $otherLocationTask->id]);
    }
}
