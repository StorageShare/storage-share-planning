<?php

namespace Feature\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskBacklogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    public function test_index_renders_view_with_expected_data(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create(['name' => 'Alpha']);
        Task::factory()->create(['location_id' => $loc->id, 'title' => 'Foo task']);

        $resp = $this->actingAs($user)->get(route('backlog.index', [
            'search_term' => 'Foo',
            'sort_by' => 'deadline',
            'sort_direction' => 'asc',
        ]));

        $resp->assertOk();
        $resp->assertViewIs('backlog.index');
        $resp->assertViewHasAll(['tasks', 'locations', 'priorities', 'filters', 'sortBy', 'sortDirection', 'searchTerm']);

        $paginator = $resp->viewData('tasks');
        $this->assertStringContainsString('sort_by=deadline', $paginator->url(2));
        $this->assertStringContainsString('sort_direction=asc', $paginator->url(2));
        $this->assertStringContainsString('search_term=Foo', $paginator->url(2));
    }

    public function test_customer_service_sees_only_concept_tasks(): void
    {
        $user = User::factory()->create(['role' => Role::CUSTOMER_SERVICE->value]);
        $loc = Location::factory()->create();
        $tConcept = Task::factory()->concept()->create(['location_id' => $loc->id, 'title' => 'Concept']);
        $tOpen = Task::factory()->open()->create(['location_id' => $loc->id, 'title' => 'Open']);
        $tInProg = Task::factory()->inProgress()->create(['location_id' => $loc->id, 'title' => 'InProg']);
        $tCompleted = Task::factory()->completed()->create(['location_id' => $loc->id, 'title' => 'Completed']);

        $resp = $this->actingAs($user)->get(route('backlog.index'));
        $resp->assertOk();

        $ids = $resp->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($tConcept->id, $ids);
        $this->assertNotContains($tOpen->id, $ids);
        $this->assertNotContains($tInProg->id, $ids);
        $this->assertNotContains($tCompleted->id, $ids);
    }

    public function test_default_excludes_completed_unless_show_completed_true(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create();
        $open = Task::factory()->open()->create(['location_id' => $loc->id]);
        $inProg = Task::factory()->inProgress()->create(['location_id' => $loc->id]);
        $concept = Task::factory()->concept()->create(['location_id' => $loc->id]);
        $completed = Task::factory()->completed()->create(['location_id' => $loc->id]);

        // Default: exclude completed
        $resp = $this->actingAs($user)->get(route('backlog.index'));
        $ids = $resp->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($open->id, $ids);
        $this->assertContains($inProg->id, $ids);
        $this->assertContains($concept->id, $ids);
        $this->assertNotContains($completed->id, $ids);

        // With show_completed=true includes completed too
        $resp2 = $this->actingAs($user)->get(route('backlog.index', ['show_completed' => true]));
        $ids2 = $resp2->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($completed->id, $ids2);
    }

    public function test_search_filters_by_title_and_description(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create();
        $matchTitle = Task::factory()->create(['location_id' => $loc->id, 'title' => 'Replace filter cartridge']);
        $matchDesc = Task::factory()->create(['location_id' => $loc->id, 'title' => 'Foo', 'description' => 'This contains magic term XYZ']);
        $nonMatch = Task::factory()->create(['location_id' => $loc->id, 'title' => 'Bar', 'description' => 'Nothing to see']);

        $resp = $this->actingAs($user)->get(route('backlog.index', ['search_term' => 'cartridge']));
        $ids = $resp->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($matchTitle->id, $ids);
        $this->assertNotContains($nonMatch->id, $ids);

        $resp2 = $this->actingAs($user)->get(route('backlog.index', ['search_term' => 'magic term XYZ']));
        $ids2 = $resp2->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($matchDesc->id, $ids2);
        $this->assertNotContains($nonMatch->id, $ids2);
    }

    public function test_filters_by_location_and_priority(): void
    {
        $user = User::factory()->create();
        $l1 = Location::factory()->create();
        $l2 = Location::factory()->create();
        $t1 = Task::factory()->create(['location_id' => $l1->id, 'priority' => TaskPriority::HIGH->value]);
        $t2 = Task::factory()->create(['location_id' => $l1->id, 'priority' => TaskPriority::LOW->value]);
        $t3 = Task::factory()->create(['location_id' => $l2->id, 'priority' => TaskPriority::HIGH->value]);

        // Filter by location
        $resp = $this->actingAs($user)->get(route('backlog.index', ['location_id' => $l1->id]));
        $ids = $resp->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($t1->id, $ids);
        $this->assertContains($t2->id, $ids);
        $this->assertNotContains($t3->id, $ids);

        // Filter by priority
        $resp2 = $this->actingAs($user)->get(route('backlog.index', ['priority' => TaskPriority::HIGH->value]));
        $ids2 = $resp2->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($t1->id, $ids2);
        $this->assertContains($t3->id, $ids2);
        $this->assertNotContains($t2->id, $ids2);
    }

    public function test_default_sorting_deadline_asc_nulls_last_then_priority_high_normal_low(): void
    {
        $user = User::factory()->create();
        $l = Location::factory()->create();
        // Create tasks with various deadlines and priorities
        $d1High = Task::factory()->create(['location_id' => $l->id, 'deadline' => '2025-10-10', 'priority' => TaskPriority::HIGH->value, 'title' => 'd1-high']);
        $d1Low = Task::factory()->create(['location_id' => $l->id, 'deadline' => '2025-10-10', 'priority' => TaskPriority::LOW->value, 'title' => 'd1-low']);
        $d2Normal = Task::factory()->create(['location_id' => $l->id, 'deadline' => '2025-10-12', 'priority' => TaskPriority::NORMAL->value, 'title' => 'd2-normal']);
        $nullDeadlineHigh = Task::factory()->create(['location_id' => $l->id, 'deadline' => null, 'priority' => TaskPriority::HIGH->value, 'title' => 'null-high']);

        $resp = $this->actingAs($user)->get(route('backlog.index'));
        $ordered = $resp->viewData('tasks')->getCollection()->pluck('title')->all();

        // Expected order:
        // - 2025-10-10 high before low
        // - then 2025-10-12 normal
        // - then null deadline last
        $this->assertSame(['d1-high', 'd1-low', 'd2-normal', 'null-high'], $ordered);
    }

    public function test_sort_by_location_name_join_and_order(): void
    {
        $user = User::factory()->create();
        $lA = Location::factory()->create(['name' => 'Aardbei']);
        $lZ = Location::factory()->create(['name' => 'Zebra']);
        $tZ = Task::factory()->create(['location_id' => $lZ->id, 'title' => 'Zebra task']);
        $tA = Task::factory()->create(['location_id' => $lA->id, 'title' => 'Aardbei task']);

        $resp = $this->actingAs($user)->get(route('backlog.index', ['sort_by' => 'location_name', 'sort_direction' => 'asc']));
        $ordered = $resp->viewData('tasks')->getCollection()->pluck('title')->all();
        $this->assertSame(['Aardbei task', 'Zebra task'], $ordered);
    }

    public function test_sort_by_priority_desc_uses_custom_case_expression(): void
    {
        $user = User::factory()->create();
        $l = Location::factory()->create();
        $high = Task::factory()->create(['location_id' => $l->id, 'priority' => TaskPriority::HIGH->value, 'title' => 'high']);
        $normal = Task::factory()->create(['location_id' => $l->id, 'priority' => TaskPriority::NORMAL->value, 'title' => 'normal']);
        $low = Task::factory()->create(['location_id' => $l->id, 'priority' => TaskPriority::LOW->value, 'title' => 'low']);

        $resp = $this->actingAs($user)->get(route('backlog.index', ['sort_by' => 'priority', 'sort_direction' => 'desc']));
        $ordered = $resp->viewData('tasks')->getCollection()->pluck('title')->all();
        // Descending with CASE(High=1,Normal=2,Low=3) means Low first, then Normal, then High
        $this->assertSame(['low', 'normal', 'high'], $ordered);
    }

    public function test_sort_by_deadline_desc_keeps_nulls_last(): void
    {
        $user = User::factory()->create();
        $l = Location::factory()->create();
        $d10 = Task::factory()->create(['location_id' => $l->id, 'deadline' => '2025-10-10', 'title' => 'd10']);
        $d12 = Task::factory()->create(['location_id' => $l->id, 'deadline' => '2025-10-12', 'title' => 'd12']);
        $dNull = Task::factory()->create(['location_id' => $l->id, 'deadline' => null, 'title' => 'dNull']);

        $resp = $this->actingAs($user)->get(route('backlog.index', ['sort_by' => 'deadline', 'sort_direction' => 'desc']));
        $ordered = $resp->viewData('tasks')->getCollection()->pluck('title')->all();
        // Descending: 12, 10, then null last
        $this->assertSame(['d12', 'd10', 'dNull'], $ordered);
    }

    public function test_unknown_sort_by_falls_back_to_created_at_desc(): void
    {
        $user = User::factory()->create();
        $l = Location::factory()->create();
        $t1 = Task::factory()->create(['location_id' => $l->id, 'title' => 'older']);
        // Simulate later creation
        $t2 = Task::factory()->create(['location_id' => $l->id, 'title' => 'newer']);

        $resp = $this->actingAs($user)->get(route('backlog.index', ['sort_by' => 'not_a_column']));
        $ordered = $resp->viewData('tasks')->getCollection()->pluck('title')->all();
        $this->assertSame(['newer', 'older'], $ordered);
    }
}
