<?php

namespace Feature\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Requirement;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\Task;
use App\Models\User;
use App\Services\TravelTimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MyPlanningControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    public function test_show_renders_empty_when_no_planning_for_today(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);

        $response = $this->actingAs($user)
            ->get(route('my-planning.show'));

        $response->assertOk();
        $response->assertViewIs('my-planning.show-empty');
    }

    public function test_show_renders_today_planning_with_summary_when_exists(): void
    {
        Carbon::setTestNow('2025-10-17 08:00:00');

        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);

        $planning = Planning::factory()->create([
            'planned_date' => Carbon::today(),
            'start_address' => 'kantoor',
        ]);
        $planning->users()->attach($user->id);

        // No tasks and no locations -> should still render summary step
        $mock = $this->createMock(TravelTimeService::class);
        // With no locations, controller won't call these, but we bind anyway
        $mock->method('calculateTravelTimesForSequence')->willReturn([]);
        $mock->method('formatDuration')->willReturn('0 min');
        $this->app->instance(TravelTimeService::class, $mock);

        $response = $this->actingAs($user)
            ->get(route('my-planning.show'));

        $response->assertOk();
        $response->assertViewIs('my-planning.show');
        $response->assertViewHasAll(['planning', 'locationSteps', 'travelTimes', 'timeOverview']);

        $response->assertViewHas('planning', function ($p) use ($planning) {
            return (int) $p->id === (int) $planning->id;
        });

        $response->assertViewHas('locationSteps', function ($steps) {
            // Should contain at least the summary step when there are no requirements/tasks
            $this->assertIsArray($steps);
            $types = array_column($steps, 'type');
            $this->assertContains('summary', $types);
            return true;
        });

        $response->assertViewHas('travelTimes', null);
        $response->assertViewHas('timeOverview', function ($ov) {
            return isset($ov['task_minutes'], $ov['travel_minutes'], $ov['total_minutes'])
                && $ov['task_minutes'] === 0
                && $ov['travel_minutes'] === 0
                && $ov['total_minutes'] === 0;
        });

        Carbon::setTestNow();
    }

    public function test_show_with_specific_planning_enforces_authorization(): void
    {
        $owner = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $intruder = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);

        $planning = Planning::factory()->create(['planned_date' => Carbon::parse('2025-10-16')]);
        $planning->users()->attach($owner->id);

        // Non-attached non-admin should be forbidden
        $forbidden = $this->actingAs($intruder)
            ->get(route('my-planning.planning', $planning));
        $forbidden->assertForbidden();

        // Admin can access
        $ok = $this->actingAs($admin)
            ->get(route('my-planning.planning', $planning));
        $ok->assertOk();
        $ok->assertViewIs('my-planning.show');
    }

    public function test_benodigdheden_checklist_includes_location_specific_and_dedupes_regular(): void
    {
        Carbon::setTestNow('2025-10-17 08:00:00');

        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create(['planned_date' => Carbon::today()]);
        $planning->users()->attach($user->id);

        // Create two locations and attach in order
        $l1 = Location::factory()->create(['name' => 'Loc A']);
        $l2 = Location::factory()->create(['name' => 'Loc B']);
        $planning->locations()->attach($l1->id, ['sort_order' => 1]);
        $planning->locations()->attach($l2->id, ['sort_order' => 2]);

        // Create requirements: one regular, one with [locatie]
        $creator = User::factory()->create();
        $regular = Requirement::create(['name' => 'Emmer', 'description' => 'Plastic', 'created_by' => $creator->id]);
        $placeholder = Requirement::create(['name' => 'Sleutel [locatie]', 'description' => 'Sleutelbos', 'created_by' => $creator->id]);

        // Mark both as automatically required for both locations
        $regular->requiredForLocations()->sync([$l1->id, $l2->id]);
        $placeholder->requiredForLocations()->sync([$l1->id, $l2->id]);

        // Also add a task that uses the same regular requirement to test de-duplication
        $task = Task::factory()->create();
        $task->requirements()->sync([$regular->id]);
        PlanningTask::create([
            'planning_id' => $planning->id,
            'task_id' => $task->id,
            'status' => TaskStatus::OPEN->value,
            'title' => 'Backlog taak',
            'description' => 'Beschrijving',
        ]);

        // Bind a harmless TravelTimeService mock
        $mock = $this->createMock(TravelTimeService::class);
        $mock->method('calculateTravelTime')->willReturn(['duration_minutes' => 0, 'distance_km' => 0.0]);
        $mock->method('formatDuration')->willReturn('0 min');
        $this->app->instance(TravelTimeService::class, $mock);

        $response = $this->actingAs($user)->get(route('my-planning.show'));
        $response->assertOk();

        $response->assertViewHas('locationSteps', function ($steps) use ($l1, $l2) {
            $this->assertIsArray($steps);
            $this->assertNotEmpty($steps);
            // First step should be the requirements checklist
            $first = $steps[0];
            $this->assertEquals('requirements', $first['type']);
            $names = array_column($first['requirements'], 'naam');
            // Regular item appears once (deduped) even though it is required for two locations and used by a task
            $this->assertEquals(1, collect($names)->filter(fn($n) => $n === 'Emmer')->count());
            // Placeholder creates two location-specific variants with replaced names
            $this->assertContains('Sleutel Loc A', $names);
            $this->assertContains('Sleutel Loc B', $names);
            return true;
        });

        Carbon::setTestNow();
    }
}
