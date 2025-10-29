<?php

namespace Feature\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationControllerTest extends TestCase
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

    public function test_index_lists_locations_with_sort_search_filter_and_counts(): void
    {
        // Create locations with predictable names
        $alpha = Location::factory()->create(['name' => 'Alpha Storage']);
        $bravo = Location::factory()->create(['name' => 'Bravo Depot']);
        $charlie = Location::factory()->create(['name' => 'Charlie Hub']);

        // Delete all tasks to reset counts
        Task::query()->delete();

        // Attach tasks with various priorities and statuses
        // Alpha: 1 high open, 1 normal open, 1 low completed (should NOT count)
        Task::factory()->forLocation($alpha)->high()->open()->create();
        Task::factory()->forLocation($alpha)->normal()->inProgress()->create();
        Task::factory()->forLocation($alpha)->low()->completed()->create();

        // Bravo: 2 low open, 1 normal rejected (should NOT count)
        Task::factory()->forLocation($bravo)->low()->open()->create();
        Task::factory()->forLocation($bravo)->low()->open()->create();
        Task::factory()->forLocation($bravo)->low()->rejected()->create();

        // Charlie: no tasks

        // Valid sort and search: search for locations containing 'a' (case-insensitive)
        $response = $this->actingAs($this->admin)
            ->get(route('locations.index', [
                'sort_by' => 'name',
                'sort_direction' => 'asc',
                'search_term' => 'A',
            ]));

        $response->assertOk();
        $response->assertViewIs('locations.index');

        $response->assertViewHas('locations', function ($paginator) use ($alpha, $bravo, $charlie) {
            $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
            // All three have an 'a' in the name (case-insensitive)
            $ids = $paginator->getCollection()->pluck('id')->all();
            $this->assertContains($alpha->id, $ids);
            $this->assertContains($bravo->id, $ids);
            $this->assertContains($charlie->id, $ids);

            // Verify counts aliases exist on models
            $alphaModel = $paginator->getCollection()->firstWhere('id', $alpha->id);
            $this->assertNotNull($alphaModel);
            $this->assertEquals(1, $alphaModel->open_tasks_high_count);
            $this->assertEquals(1, $alphaModel->open_tasks_normal_count);
            $this->assertEquals(0, $alphaModel->open_tasks_low_count);

            $bravoModel = $paginator->getCollection()->firstWhere('id', $bravo->id);
            $this->assertNotNull($bravoModel);
            $this->assertEquals(0, $bravoModel->open_tasks_high_count);
            $this->assertEquals(0, $bravoModel->open_tasks_normal_count);
            $this->assertEquals(2, $bravoModel->open_tasks_low_count);

            return true;
        });

        // Filter: only locations that have open tasks
        $filtered = $this->actingAs($this->admin)
            ->get(route('locations.index', [
                'filter' => 'with_open_tasks',
                'sort_by' => 'name',
                'sort_direction' => 'asc',
            ]));

        $filtered->assertOk();
        $filtered->assertViewHas('locations', function ($paginator) use ($alpha, $bravo, $charlie) {
            $ids = $paginator->getCollection()->pluck('id')->all();
            // Charlie has no open tasks, should be excluded
            $this->assertContains($alpha->id, $ids);
            $this->assertContains($bravo->id, $ids);
            $this->assertNotContains($charlie->id, $ids);
            return true;
        });

        // Invalid sort parameters should not crash and should default silently
        $invalid = $this->actingAs($this->admin)
            ->get(route('locations.index', [
                'sort_by' => 'INVALID_COLUMN',
                'sort_direction' => 'NOPE',
                'search_term' => '',
            ]));
        $invalid->assertOk();

        // Pagination appends: ensure query parameters are present on next page url
        $withQuery = $this->actingAs($this->admin)
            ->get(route('locations.index', [
                'sort_by' => 'name',
                'sort_direction' => 'asc',
                'search_term' => 'a',
            ]));
        /** @var LengthAwarePaginator $paginator */
        $paginator = $withQuery->viewData('locations');
        $this->assertStringContainsString('sort_by=name', $paginator->url(2));
        $this->assertStringContainsString('sort_direction=asc', $paginator->url(2));
        $this->assertStringContainsString('search_term=a', $paginator->url(2));
    }

    public function test_show_displays_open_tasks_with_expected_order_and_pagination(): void
    {
        $loc = Location::factory()->create();
        Task::query()->delete();

        // Freeze time for deterministic created_at ordering
        Carbon::setTestNow('2025-10-15 12:00:00');

        // Helper to create a task with flexible attributes and optional created_at offset
        $make = function (array $attrs = [], int $minutesAgo = 0) use ($loc) {
            $t = Task::factory()->create(array_merge([
                'location_id' => $loc->id,
                'status' => TaskStatus::OPEN->value,
                'priority' => TaskPriority::NORMAL->value,
                'deadline' => null,
            ], $attrs));
            if ($minutesAgo !== 0) {
                $t->created_at = Carbon::now()->subMinutes($minutesAgo);
                $t->save();
            }
            return $t;
        };

        // Include tasks to verify filtering and ordering
        // With deadlines: today high, today normal, tomorrow low
        $t1 = $make(['deadline' => Carbon::today(), 'priority' => TaskPriority::HIGH->value]);
        $t2 = $make(['deadline' => Carbon::today(), 'priority' => TaskPriority::NORMAL->value]);
        $t3 = $make(['deadline' => Carbon::tomorrow(), 'priority' => TaskPriority::LOW->value]);

        // Without deadline: high, normal, low (created_at different to test desc)
        $t4 = $make(['priority' => TaskPriority::HIGH->value], 30); // older
        $t5 = $make(['priority' => TaskPriority::LOW->value], 5);  // newer
        $t6 = $make(['priority' => TaskPriority::NORMAL->value], 10);

        // Excluded statuses
        $t7 = $make(['status' => TaskStatus::COMPLETED->value]);
        $t8 = $make(['status' => TaskStatus::REJECTED->value]);

        $response = $this->actingAs($this->admin)
            ->get(route('locations.show', $loc));

        $response->assertOk();
        $response->assertViewIs('locations.show');
        $response->assertViewHas('location', function ($l) use ($loc) {
            return $l->id === $loc->id;
        });

        $response->assertViewHas('open_tasks', function ($paginator) use ($t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8) {
            $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
            $ids = $paginator->getCollection()->pluck('id')->all();

            // Exclusions
            $this->assertNotContains($t7->id, $ids);
            $this->assertNotContains($t8->id, $ids);

            // Expected order on the page:
            // 1) With deadline first by date asc and priority (high before normal before low)
            // Today high (t1), then today normal (t2), then tomorrow low (t3)
            // 2) No deadline next, by priority (high, normal, low), then created_at desc within same priority
            // Here, t4(high older), t6(normal), t5(low newer)
            $expectedOrder = [$t1->id, $t2->id, $t3->id, $t4->id, $t6->id, $t5->id];
            $this->assertSame($expectedOrder, array_slice($ids, 0, count($expectedOrder)));

            // Pagination size 10 (we created fewer than 10 open tasks)
            $this->assertLessThanOrEqual(10, count($ids));

            return true;
        });

        // Test pagination with more than 10 tasks
        // Add 10 more OPEN tasks without deadlines so total open > 10
        for ($i = 0; $i < 10; $i++) {
            $make();
        }

        $page1 = $this->actingAs($this->admin)->get(route('locations.show', ['location' => $loc->id]));
        /** @var LengthAwarePaginator $p1 */
        $p1 = $page1->viewData('open_tasks');
        $this->assertCount(10, $p1->items());

        $page2 = $this->actingAs($this->admin)->get(route('locations.show', ['location' => $loc->id, 'page' => 2]));
        /** @var LengthAwarePaginator $p2 */
        $p2 = $page2->viewData('open_tasks');
        $this->assertGreaterThan(0, count($p2->items()));

        Carbon::setTestNow();
    }

    public function test_show_returns_404_for_soft_deleted_location(): void
    {
        $loc = Location::factory()->create();
        $loc->delete(); // Soft delete

        $response = $this->actingAs($this->admin)
            ->get(route('locations.show', $loc));

        $response->assertNotFound();
    }
}
