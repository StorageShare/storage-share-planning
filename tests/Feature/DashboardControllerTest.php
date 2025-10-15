<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);

        // Freeze time for deterministic date buckets
        Carbon::setTestNow(Carbon::parse('2025-10-15 10:00:00')); // Wednesday
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function test_admin_sees_correct_planning_buckets_and_counts(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);

        // Create some plannings across the buckets
        $today = Carbon::today();
        $tomorrow = Carbon::today()->addDay();
        $endOfWeek = Carbon::today()->endOfWeek();
        $startOfNextWeek = Carbon::today()->addWeek()->startOfWeek();
        $endOfNextWeek = Carbon::today()->addWeek()->endOfWeek();

        // Helper to create a planning for a given date
        $makePlanning = function (Carbon $date) {
            return Planning::factory()->create([
                'planned_date' => $date->copy(),
            ]);
        };

        // Today (2)
        $pToday1 = $makePlanning($today);
        $pToday2 = $makePlanning($today);

        // Rest of week (2)
        $pRest1 = $makePlanning($tomorrow);
        $pRest2 = $makePlanning($endOfWeek);

        // Next week (2)
        $pNext1 = $makePlanning($startOfNextWeek);
        $pNext2 = $makePlanning($endOfNextWeek);

        // Backlog open tasks without planningTasks (3)
        Task::factory()->open()->create();
        Task::factory()->open()->create();
        Task::factory()->open()->create();

        // One open task that IS linked to a planning - should NOT be counted in backlog_open_tasks
        $linkedTask = Task::factory()->open()->create();
        PlanningTask::create([
            'planning_id' => $pToday1->id,
            'task_id' => $linkedTask->id,
            'status' => TaskStatus::OPEN->value,
            'title' => 'Linked open task',
            'description' => 'Linked task',
        ]);

        // Review counts: one Task in REVIEW and two PlanningTasks in REVIEW (without task_id)
        Task::factory()->create(['status' => TaskStatus::REVIEW->value]);
        PlanningTask::create([
            'planning_id' => $pRest1->id,
            'task_id' => null,
            'status' => TaskStatus::REVIEW->value,
            'title' => 'Review planning task 1',
            'description' => 'Review planning task 1',
        ]);
        PlanningTask::create([
            'planning_id' => $pNext1->id,
            'task_id' => null,
            'status' => TaskStatus::REVIEW->value,
            'title' => 'Review planning task 2',
            'description' => 'Review planning task 2',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');

        // Verify buckets
        $response->assertViewHas('todays_plannings', function ($c) use ($pToday1, $pToday2) {
            return $c->count() === 2 && $c->pluck('id')->sort()->values()->all() === collect([$pToday1->id, $pToday2->id])->sort()->values()->all();
        });

        $response->assertViewHas('plannings_rest_of_week', function ($c) use ($pRest1, $pRest2) {
            return $c->count() === 2 && $c->pluck('id')->sort()->values()->all() === collect([$pRest1->id, $pRest2->id])->sort()->values()->all();
        });

        $response->assertViewHas('plannings_next_week', function ($c) use ($pNext1, $pNext2) {
            return $c->count() === 2 && $c->pluck('id')->sort()->values()->all() === collect([$pNext1->id, $pNext2->id])->sort()->values()->all();
        });

        // Count for this week (today + rest)
        $response->assertViewHas('plannings_this_week_count', 4);

        // Backlog open tasks: 3 (linked one excluded)
        $response->assertViewHas('backlog_open_tasks', 3);

        // Tasks for review: 1 Task(REVIEW) + 2 PlanningTask(REVIEW) = 3
        $response->assertViewHas('tasks_for_review_count', 3);
    }

    public function test_non_admin_sees_only_their_plannings_and_review_counts(): void
    {
        $user = User::factory()->create(); // default non-admin
        $otherUser = User::factory()->create();

        $today = Carbon::today();
        $tomorrow = Carbon::today()->addDay();
        $startOfNextWeek = Carbon::today()->addWeek()->startOfWeek();

        // Create plannings and attach users accordingly
        $pUserToday = Planning::factory()->create(['planned_date' => $today]);
        $pUserRest = Planning::factory()->create(['planned_date' => $tomorrow]);
        $pUserNext = Planning::factory()->create(['planned_date' => $startOfNextWeek]);

        $pOtherToday = Planning::factory()->create(['planned_date' => $today]);

        $pUserToday->users()->attach($user->id);
        $pUserRest->users()->attach($user->id);
        $pUserNext->users()->attach($user->id);
        $pOtherToday->users()->attach($otherUser->id);

        // PlanningTasks in REVIEW: only the one linked to user's planning should be counted
        PlanningTask::create([
            'planning_id' => $pUserRest->id,
            'task_id' => null,
            'status' => TaskStatus::REVIEW->value,
            'title' => 'User review PT',
            'description' => 'User review PT',
        ]);
        PlanningTask::create([
            'planning_id' => $pOtherToday->id,
            'task_id' => null,
            'status' => TaskStatus::REVIEW->value,
            'title' => 'Other user review PT',
            'description' => 'Other user review PT',
        ]);

        // Backlog open tasks should be 0 for non-admin regardless of data
        Task::factory()->open()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');

        // Buckets should only include user's plannings
        $response->assertViewHas('todays_plannings', function ($c) use ($pUserToday) {
            return $c->count() === 1 && $c->first()->id === $pUserToday->id;
        });
        $response->assertViewHas('plannings_rest_of_week', function ($c) use ($pUserRest) {
            return $c->count() === 1 && $c->first()->id === $pUserRest->id;
        });
        $response->assertViewHas('plannings_next_week', function ($c) use ($pUserNext) {
            return $c->count() === 1 && $c->first()->id === $pUserNext->id;
        });

        // Weekly count (today + rest)
        $response->assertViewHas('plannings_this_week_count', 2);

        // Non-admin backlog open tasks = 0
        $response->assertViewHas('backlog_open_tasks', 0);

        // tasks_for_review_count: only 1 planning task for the user's planning
        $response->assertViewHas('tasks_for_review_count', 1);
    }
}
