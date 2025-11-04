<?php

namespace Feature\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Mail\PlanningReadyNotificationMail;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningLocationTimer;
use App\Models\PlanningTask;
use App\Models\Task;
use App\Models\User;
use App\Services\TravelTimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
        $this->admin = User::factory()->create(['role' => Role::ADMIN->value]);
    }

    public function test_index_admin_and_non_admin_scoping_and_query_params(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // Create plannings and attach users
        $p1 = Planning::factory()->create(['planned_date' => Carbon::parse('2025-10-15 09:00:00'), 'status' => 'open']);
        $p2 = Planning::factory()->create(['planned_date' => Carbon::parse('2025-10-16 09:00:00'), 'status' => 'completed']);
        $p3 = Planning::factory()->create(['planned_date' => Carbon::parse('2025-10-17 09:00:00'), 'status' => 'open']);
        $p1->users()->attach($user->id);
        $p2->users()->attach($other->id);
        $p3->users()->attach($user->id);

        // Admin sees all and can filter/search/sort
        $resp = $this->actingAs($this->admin)->get(route('plannings.index', [
            'filter' => 'open',
            'search_term' => '',
            'sort_by' => 'planned_date',
            'sort_direction' => 'asc',
        ]));
        $resp->assertOk();
        $resp->assertViewIs('plannings.index');
        $resp->assertViewHas('plannings', function ($paginator) use ($p1, $p3) {
            $ids = $paginator->getCollection()->pluck('id')->all();
            // only open are p1 and p3 and sorted asc by date
            $this->assertEquals([$p1->id, $p3->id], array_values($ids));
            return true;
        });

        // Non admin cannot index plannings
        $userResp = $this->actingAs($user)->get(route('plannings.index'));
        $userResp->assertForbidden();
    }

    public function test_create_renders_expected_data_structures(): void
    {
        $users = User::factory()->count(2)->create();
        $loc1 = Location::factory()->create();
        $loc2 = Location::factory()->create();

        // Default tasks: one global, one attached to loc1 only
        $global = DefaultTask::create(['title' => 'Global', 'description' => '', 'applies_to_all_locations' => true]);
        $local = DefaultTask::create(['title' => 'Local', 'description' => '']);
        $local->locations()->sync([$loc1->id]);

        // Backlog tasks for both locations
        Task::factory()->create(['location_id' => $loc1->id, 'priority' => TaskPriority::HIGH->value]);
        Task::factory()->create(['location_id' => $loc2->id, 'priority' => TaskPriority::NORMAL->value]);

        $resp = $this->actingAs($this->admin)->get(route('plannings.create', ['location_id' => $loc1->id]));
        $resp->assertOk();
        $resp->assertViewIs('plannings.create');
        $resp->assertViewHasAll([
            'locations',
            'defaultTasksByLocation',
            'backlogTasksByLocation',
            'backlogPriorityCountsByLocation',
            'backlogTotalEstimatedTimeByLocation',
            'selected_location_id',
            'users',
            'plannedBacklogTasks',
        ]);

        // Ensure defaultTasksByLocation combines global+local for loc1
        $map = $resp->viewData('defaultTasksByLocation');
        $this->assertArrayHasKey($loc1->id, $map->toArray());
        $taskTitles = collect($map[$loc1->id])->pluck('title')->all();
        $this->assertContains('Global', $taskTitles);
        $this->assertContains('Local', $taskTitles);
    }

    public function test_store_creates_planning_syncs_order_and_tasks_and_users(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $l1 = Location::factory()->create();
        $l2 = Location::factory()->create();

        $dt1 = DefaultTask::create(['title' => 'DT1', 'description' => '']);
        $dt1->locations()->sync([$l1->id]);

        $t1 = Task::factory()->create(['location_id' => $l2->id]);

        $payload = [
            'planned_date' => '2025-10-20',
            'notes' => 'Note',
            'start_address_option' => 'Kantoor',
            'start_address' => 'Kantoor',
            'start_time' => '08:00',
            'location_ids' => [$l1->id, $l2->id],
            'location_order' => $l2->id . ',' . $l1->id, // reverse order
            'user_ids' => [$u1->id, $u2->id],
            'selected_default_tasks' => [$dt1->id],
            'selected_backlog_tasks' => [$t1->id],
        ];

        $resp = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.store'), $payload);

        $resp->assertRedirect(route('plannings.index'));
        $resp->assertSessionHas('success');

        $planning = Planning::first();
        $this->assertNotNull($planning);

        // Order preserved via sort_order on pivot (0-based in controller)
        $attached = $planning->locations()->pluck('locations.id', 'location_planning.sort_order')->all();
        $this->assertEquals([0 => $l2->id, 1 => $l1->id], $attached);

        // Users synced
        $this->assertEqualsCanonicalizing([$u1->id, $u2->id], $planning->users()->pluck('users.id')->all());

        // Planning tasks created: one for default task at l1 and one backlog t1
        $this->assertDatabaseHas('planning_tasks', [
            'planning_id' => $planning->id,
            'default_task_id' => $dt1->id,
            'location_id' => $l1->id,
        ]);
        $this->assertDatabaseHas('planning_tasks', [
            'planning_id' => $planning->id,
            'task_id' => $t1->id,
            'location_id' => $l2->id,
        ]);
    }

    public function test_show_calculates_time_overview_and_uses_travel_service_and_timers(): void
    {
        $defaultTask = DefaultTask::factory()->create();
        $planning = Planning::factory()->create();
        $l1 = Location::factory()->create();
        $l2 = Location::factory()->create();
        $planning->locations()->attach([$l1->id => ['sort_order' => 0], $l2->id => ['sort_order' => 1]]);

        // Create planning tasks: default-like and backlog-like with time estimates
        $tBacklog = Task::factory()->create(['location_id' => $l1->id]);
        PlanningTask::create([
            'planning_id' => $planning->id,
            'task_id' => $tBacklog->id,
            'title' => 'Backlog',
            'description' => '',
        ]);
        // Fake default task estimated minutes by setting attributes directly
        PlanningTask::create([
            'planning_id' => $planning->id,
            'default_task_id' => $defaultTask->id,
            'location_id' => $l2->id,
            'title' => 'DefaultLike',
            'description' => '',
        ]);

        // Bind a mock TravelTimeService
        $this->app->bind(TravelTimeService::class, function () {
            return new class {
                public function calculateTravelTimesForSequence($locations, $startAddress = null)
                {
                    return [
                        'total_duration_minutes' => 25,
                        'total_duration_formatted' => '25 min',
                        'segments' => [
                            ['from' => 'A', 'to' => 'B', 'duration_minutes' => 25, 'distance_km' => 10, 'index' => 'return', 'error' => null, 'is_return', true],
                        ],
                    ];
                }
            };
        });

        // Add a timer entry to assert mapping
        PlanningLocationTimer::create([
            'planning_id' => $planning->id,
            'location_id' => $l1->id,
            'location_type' => 'location',
            'started_at' => now()->subMinutes(30),
            'ended_at' => now()->subMinutes(10),
            'total_duration_seconds' => 1200,
        ]);

        $resp = $this->actingAs($this->admin)->get(route('plannings.show', $planning));
        $resp->assertOk();
        $resp->assertViewIs('plannings.show');
        $resp->assertViewHasAll(['planning', 'travelTimes', 'timeOverview', 'locationTimers']);
        $ov = $resp->viewData('timeOverview');
        $this->assertEquals(25, $ov['travel_minutes']);
        // Task minutes may be zero (estimated fields not set on related models in this simplified case)
        $this->assertArrayHasKey('task_minutes', $ov);
        $timers = $resp->viewData('locationTimers');
        $this->assertArrayHasKey($l1->id, $timers->toArray());
    }

    public function test_edit_renders_and_includes_current_selections(): void
    {
        $planning = Planning::factory()->create();
        $l1 = Location::factory()->create();
        $l2 = Location::factory()->create();
        $planning->locations()->attach([$l2->id => ['sort_order' => 0], $l1->id => ['sort_order' => 1]]);

        $dt = DefaultTask::create(['title' => 'DT', 'description' => '']);
        $dt->locations()->sync([$l2->id]);
        $task = Task::factory()->create(['location_id' => $l1->id]);

        // Link one default and one backlog to planning
        PlanningTask::create([
            'planning_id' => $planning->id,
            'default_task_id' => $dt->id,
            'location_id' => $l2->id,
            'title' => 'DT linked',
            'description' => '',
        ]);
        PlanningTask::create([
            'planning_id' => $planning->id,
            'task_id' => $task->id,
            'location_id' => $l1->id,
            'title' => 'BL linked',
            'description' => '',
        ]);

        $resp = $this->actingAs($this->admin)->get(route('plannings.edit', $planning));
        $resp->assertOk();
        $resp->assertViewIs('plannings.edit');
        $resp->assertViewHasAll([
            'planning',
            'locations',
            'defaultTasksByLocation',
            'backlogTasksByLocation',
            'backlogPriorityCountsByLocation',
            'backlogTotalEstimatedTimeByLocation',
            'current_selected_location_ids',
            'current_selected_default_tasks',
            'current_selected_backlog_tasks',
            'users',
            'plannedBacklogTasks',
        ]);

        $this->assertContains($l1->id, $resp->viewData('current_selected_location_ids'));
        $this->assertContains((string)$dt->id, $resp->viewData('current_selected_default_tasks'));
        $this->assertContains((string)$task->id, $resp->viewData('current_selected_backlog_tasks'));
    }

    public function test_update_updates_fields_location_order_users_and_tasks(): void
    {
        $planning = Planning::factory()->create(['planned_date' => '2025-10-20', 'notes' => 'Old']);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $l1 = Location::factory()->create();
        $l2 = Location::factory()->create();
        $planning->locations()->attach([$l1->id => ['sort_order' => 0]]);

        $dt = DefaultTask::create(['title' => 'DT', 'description' => '']);
        $dt->locations()->sync([$l2->id]);
        $task = Task::factory()->create(['location_id' => $l2->id]);

        $payload = [
            'planned_date' => '2025-10-21',
            'notes' => 'New',
            'start_address_option' => 'Kantoor',
            'start_address' => 'Kantoor',
            'start_time' => '09:00',
            'location_ids' => [$l2->id, $l1->id],
            'location_order' => $l1->id . ',' . $l2->id, // swap order again
            'user_ids' => [$u1->id, $u2->id],
            'selected_default_tasks' => [$dt->id],
            'selected_backlog_tasks' => [$task->id],
        ];

        $resp = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->put(route('plannings.update', $planning), $payload);

        $resp->assertRedirect(route('plannings.show', $planning));
        $resp->assertSessionHas('success');

        $planning->refresh();
        $this->assertEquals('2025-10-21 00:00:00', $planning->planned_date->format('Y-m-d H:i:s'));
        $this->assertEquals('New', $planning->notes);

        $attached = $planning->locations()->pluck('locations.id', 'location_planning.sort_order')->all();
        $this->assertEquals([0 => $l1->id, 1 => $l2->id], $attached);

        $this->assertEqualsCanonicalizing([$u1->id, $u2->id], $planning->users()->pluck('users.id')->all());

        $this->assertDatabaseHas('planning_tasks', [
            'planning_id' => $planning->id,
            'default_task_id' => $dt->id,
            'location_id' => $l2->id,
        ]);
        $this->assertDatabaseHas('planning_tasks', [
            'planning_id' => $planning->id,
            'task_id' => $task->id,
        ]);
    }

    public function test_destroy_deletes_and_redirects(): void
    {
        $planning = Planning::factory()->create();
        $resp = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->delete(route('plannings.destroy', $planning));
        $resp->assertRedirect(route('plannings.index'));
        $resp->assertSessionHas('success');
        $this->assertDatabaseMissing('plannings', ['id' => $planning->id]);
    }

    public function test_send_notifications_sends_to_assigned_and_handles_no_users(): void
    {
        Mail::fake();

        $planning = Planning::factory()->create();
        $u = User::factory()->create(['email' => 'a@example.com']);
        $planning->users()->attach($u->id);

        $resp = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.send-notifications', $planning));
        $resp->assertSessionHas('success');
        Mail::assertSent(PlanningReadyNotificationMail::class, 1);

        // No users scenario
        $planning2 = Planning::factory()->create();
        $resp2 = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.send-notifications', $planning2));
        $resp2->assertSessionHas('error');
    }

    public function test_travel_back_timer_can_start_stop_and_be_retrieved(): void
    {
        $planning = Planning::factory()->create();

        // Start the return travel timer
        $startResp = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post("/plannings/{$planning->id}/locations/travel_back/timer/start");
        $startResp->assertOk();
        $startResp->assertJson(['success' => true]);

        // Stop the return travel timer with 5 minutes
        $stopResp = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post("/plannings/{$planning->id}/locations/travel_back/timer/stop", [
                'total_duration' => 300,
            ]);
        $stopResp->assertOk();
        $stopResp->assertJson(['success' => true]);

        $this->assertDatabaseHas('planning_location_timers', [
            'planning_id' => $planning->id,
            'location_id' => null,
            'location_type' => 'travel_back',
            'total_duration_seconds' => 300,
        ]);

        // Retrieve the timer
        $getResp = $this->actingAs($this->admin)
            ->get("/plannings/{$planning->id}/locations/travel_back/timer");
        $getResp->assertOk();
        $this->assertEquals(300, $getResp->json('total_duration'));
    }
    public function test_awaiting_approval_filter_returns_only_plannings_with_review_tasks_and_pagination_preserves_param(): void
    {
        // Create three plannings
        $p1 = Planning::factory()->create(['status' => 'open']);
        $p2 = Planning::factory()->create(['status' => 'open']);
        $p3 = Planning::factory()->create(['status' => 'open']);

        // Attach planning tasks: p1 has a task in review; p2 has a task open; p3 has none
        PlanningTask::create([
            'planning_id' => $p1->id,
            'title' => 'T1',
            'description' => '',
            'status' => \App\Enums\TaskStatus::REVIEW->value,
        ]);
        PlanningTask::create([
            'planning_id' => $p2->id,
            'title' => 'T2',
            'description' => '',
            'status' => \App\Enums\TaskStatus::OPEN->value,
        ]);

        // Request with awaiting_approval
        $resp = $this->actingAs($this->admin)->get(route('plannings.index', [
            'awaiting_approval' => 1,
            'sort_by' => 'planned_date',
            'sort_direction' => 'desc',
        ]));

        $resp->assertOk();
        $resp->assertViewIs('plannings.index');
        $resp->assertViewHas('plannings', function ($paginator) use ($p1) {
            $ids = $paginator->getCollection()->pluck('id')->all();
            $this->assertContains($p1->id, $ids);
            $this->assertCount(1, $ids);
            // pagination URL should preserve awaiting_approval
            $this->assertStringContainsString('awaiting_approval=1', $paginator->url(2));
            return true;
        });
    }
}
