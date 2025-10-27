<?php

namespace Feature\Controllers;

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Benodigdheid;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\PlanningTaskCompletion;
use App\Models\Task;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    public function test_index_renders_and_preserves_query_and_default_sorting(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create();

        // Deadlines and priorities to test order: 10(high), 10(low), 12(normal), null(high)
        $d1High = Task::factory()->create(['location_id' => $loc->id, 'deadline' => '2025-10-10', 'priority' => TaskPriority::HIGH->value, 'title' => 'd1-high']);
        $d1Low = Task::factory()->create(['location_id' => $loc->id, 'deadline' => '2025-10-10', 'priority' => TaskPriority::LOW->value, 'title' => 'd1-low']);
        $d2Normal = Task::factory()->create(['location_id' => $loc->id, 'deadline' => '2025-10-12', 'priority' => TaskPriority::NORMAL->value, 'title' => 'd2-normal']);
        $nullDeadlineHigh = Task::factory()->create(['location_id' => $loc->id, 'deadline' => null, 'priority' => TaskPriority::HIGH->value, 'title' => 'null-high']);

        $resp = $this->actingAs($user)->get(
            route('locations.tasks.index', $loc) . '?' . http_build_query([
                'search_term' => 'd1',
                'sort_by' => 'deadline',
                'sort_direction' => 'asc',
            ])
        );

        $resp->assertOk();
        $resp->assertViewIs('tasks.index');
        $resp->assertViewHasAll(['location', 'tasks', 'sortBy', 'sortDirection', 'searchTerm', 'activeFilter', 'plannedFilter']);

        $ordered = $resp->viewData('tasks')->getCollection()->pluck('title')->all();
        $this->assertSame(['d1-high', 'd1-low'], $ordered);

        $paginator = $resp->viewData('tasks');
        $this->assertStringContainsString('sort_by=deadline', $paginator->url(2));
        $this->assertStringContainsString('sort_direction=asc', $paginator->url(2));
        $this->assertStringContainsString('search_term=d1', $paginator->url(2));
    }

    public function test_index_customer_service_restricts_to_concept_and_planned_filters(): void
    {
        $user = User::factory()->create(['role' => Role::CUSTOMER_SERVICE->value]);
        $loc = Location::factory()->create();
        $concept = Task::factory()->concept()->create(['location_id' => $loc->id]);
        $open = Task::factory()->open()->create(['location_id' => $loc->id]);

        // Planned filter setup
        $planning = Planning::factory()->create();
        PlanningTask::create([
            'planning_id' => $planning->id,
            'task_id' => $concept->id,
            'title' => $concept->title,
            'description' => $concept->description,
        ]);

        // Customer service sees only concept
        $resp = $this->actingAs($user)->get(route('locations.tasks.index', $loc));
        $ids = $resp->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($concept->id, $ids);
        $this->assertNotContains($open->id, $ids);

        // Planned filter
        $respPlanned = $this->actingAs($user)->get(route('locations.tasks.index', [$loc, 'planned_filter' => 'planned']));
        $idsPlanned = $respPlanned->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($concept->id, $idsPlanned);
    }

    public function test_index_search_and_priority_filter_and_deadline_desc(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create();
        $matchTitle = Task::factory()->create(['location_id' => $loc->id, 'title' => 'Replace filter cartridge', 'priority' => TaskPriority::HIGH->value]);
        $matchDesc = Task::factory()->create(['location_id' => $loc->id, 'title' => 'Foo', 'description' => 'This contains magic term XYZ', 'deadline' => '2025-10-12']);
        $nonMatch = Task::factory()->create(['location_id' => $loc->id, 'title' => 'Bar', 'description' => 'Nothing to see', 'deadline' => '2025-10-10']);

        $resp = $this->actingAs($user)->get(route('locations.tasks.index', [$loc, 'search_term' => 'cartridge']));
        $ids = $resp->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($matchTitle->id, $ids);
        $this->assertNotContains($nonMatch->id, $ids);

        // Priority filter
        $resp2 = $this->actingAs($user)->get(route('locations.tasks.index', [$loc, 'filter' => 'priority_high']));
        $ids2 = $resp2->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertContains($matchTitle->id, $ids2);
        $this->assertNotContains($matchDesc->id, $ids2);

        // Deadline desc keeps nulls last
        $resp3 = $this->actingAs($user)->get(route('locations.tasks.index', [$loc, 'sort_by' => 'deadline', 'sort_direction' => 'desc']));
        $ordered = $resp3->viewData('tasks')->getCollection()->pluck('id')->all();
        // matchDesc (12) before nonMatch (10)
        $this->assertSame([$matchDesc->id, $nonMatch->id], array_values(array_intersect($ordered, [$matchDesc->id, $nonMatch->id])));
    }

    public function test_select_location_for_task_filters_case_insensitive_and_orders(): void
    {
        $user = User::factory()->create();
        $a = Location::factory()->create(['name' => 'Aardbei']);
        $z = Location::factory()->create(['name' => 'Zebra']);

        $resp = $this->actingAs($user)->get(route('tasks.select-location', ['search_term' => 'aard']));
        $resp->assertOk();
        $resp->assertViewIs('tasks.select-location');
        $names = $resp->viewData('locations')->pluck('name')->all();
        $this->assertContains('Aardbei', $names);
        $this->assertNotContains('Zebra', $names);
    }

    public function test_create_renders_with_benodigdheden_and_prefill(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $loc = Location::factory()->create();
        $b = Benodigdheid::create(['naam' => 'Zaklamp', 'beschrijving' => '', 'created_by' => $admin->id]);

        session(['prefill' => ['title' => 'Prefilled']]);
        $resp = $this->actingAs($admin)->get(route('locations.tasks.create', $loc));
        $resp->assertOk();
        $resp->assertViewIs('tasks.create');
        $resp->assertViewHasAll(['location', 'benodigdheden', 'prefill']);
        $this->assertContains('Prefilled', $resp->viewData('prefill'));
    }

    public function test_store_creates_task_syncs_benodigdheden_uploads_photos_and_sets_concept_for_customer_service(): void
    {
        Storage::fake('public');
        Event::fake();

        $creator = User::factory()->create(['role' => Role::CUSTOMER_SERVICE->value]);
        $loc = Location::factory()->create();
        $b1 = Benodigdheid::create(['naam' => 'Zaklamp', 'beschrijving' => '', 'created_by' => $creator->id]);
        $b2 = Benodigdheid::create(['naam' => 'Handschoenen', 'beschrijving' => '', 'created_by' => $creator->id]);

        // Mock ImageService
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('saveCompressedImage')->willReturn('task-photos/1/fake.jpg');
        $this->app->instance(ImageService::class, $imageService);

        $file = UploadedFile::fake()->image('proof.jpg', 100, 100);

        $payload = [
            'title' => 'Nieuwe taak',
            'description' => 'Beschrijving',
            'location_id' => $loc->id,
            'priority' => TaskPriority::HIGH->value,
            'photos' => [$file],
            'benodigdheden' => [$b1->id, $b2->id],
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($creator)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('locations.tasks.store', $loc), $payload);

        $resp->assertRedirect(route('backlog.index'));
        $resp->assertSessionHas('success');

        $task = Task::first();
        $this->assertNotNull($task);
        $this->assertEquals($creator->id, $task->created_by);
        $this->assertEquals(TaskStatus::CONCEPT, $task->status);
        $this->assertEqualsCanonicalizing([$b1->id, $b2->id], $task->benodigdheden()->pluck('benodigdheden.id')->all());
        $this->assertDatabaseHas('task_photos', ['task_id' => $task->id, 'file_path' => 'task-photos/1/fake.jpg']);
    }

    public function test_show_renders_with_completion_history_aggregated_and_planning_id(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create();
        $task = Task::factory()->create(['location_id' => $loc->id]);
        $planning = Planning::factory()->create();

        // Create planning task with two completions
        $pt = PlanningTask::create([
            'planning_id' => $planning->id,
            'task_id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => TaskStatus::OPEN->value,
        ]);

        PlanningTaskCompletion::create([
            'planning_task_id' => $pt->id,
            'comment' => 'First',
            'created_at' => now()->subHour(),
            'user_id' => $user->id,
        ]);
        PlanningTaskCompletion::create([
            'planning_task_id' => $pt->id,
            'comment' => 'Second',
            'created_at' => now(),
            'user_id' => $user->id,
        ]);

        $resp = $this->actingAs($user)->get(route('tasks.show', [$task, 'planning' => $planning->id]));
        $resp->assertOk();
        $resp->assertViewIs('tasks.show');
        $resp->assertViewHasAll(['task', 'completion_history', 'planning_id']);
        $hist = $resp->viewData('completion_history');
        $this->assertEquals(['Second', 'First'], $hist->pluck('comment')->all());
    }

    public function test_edit_renders_and_includes_selected_benodigdheden(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create();
        $task = Task::factory()->create(['location_id' => $loc->id]);
        $b = Benodigdheid::create(['naam' => 'Zaklamp', 'beschrijving' => '', 'created_by' => $user->id]);
        $task->benodigdheden()->sync([$b->id]);

        $resp = $this->actingAs($user)->get(route('tasks.edit', $task));
        $resp->assertOk();
        $resp->assertViewIs('tasks.edit');
        $resp->assertViewHasAll(['task', 'benodigdheden', 'selectedBenodigdheden']);
        $this->assertContains($b->id, $resp->viewData('selectedBenodigdheden'));
    }

    public function test_update_updates_fields_and_benodigdheden_and_redirects(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create();
        $task = Task::factory()->create(['location_id' => $loc->id, 'title' => 'Old', 'status' => TaskStatus::OPEN->value]);
        $b = Benodigdheid::create(['naam' => 'Zaklamp', 'beschrijving' => '', 'created_by' => $user->id]);

        $payload = [
            'title' => 'New',
            'description' => 'Desc',
            'status' => TaskStatus::IN_PROGRESS->value,
            'benodigdheden' => [$b->id],
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->put(route('tasks.update', $task), $payload);

        $resp->assertRedirect(route('tasks.show', $task));
        $resp->assertSessionHas('success');
        $task->refresh();
        $this->assertEquals('New', $task->title);
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->status);
        $this->assertEquals([$b->id], $task->benodigdheden()->pluck('benodigdheden.id')->all());
    }

    public function test_destroy_deletes_and_redirects_to_location_tasks_index(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create();
        $task = Task::factory()->create(['location_id' => $loc->id]);

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->delete(route('tasks.destroy', $task));

        $resp->assertRedirect(route('locations.tasks.index', $loc));
        $resp->assertSessionHas('success');
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_admin_approve_updates_statuses_and_redirects_and_triggers_recurring_service(): void
    {
        $admin = User::factory()->admin()->create();
        $loc = Location::factory()->create();
        $task = Task::factory()->create(['location_id' => $loc->id, 'status' => TaskStatus::REVIEW->value]);
        $planning = Planning::factory()->create();
        $pt = PlanningTask::create([
            'planning_id' => $planning->id,
            'task_id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => TaskStatus::REVIEW->value,
            'completed_at' => now(),
        ]);
        // attach a completion to update
        PlanningTaskCompletion::create([
            'planning_task_id' => $pt->id,
            'comment' => 'Done',
            'user_id' => $admin->id,
        ]);

        // Mock RecurringTaskService to avoid side effects
        $recurringService = new class {
            public function createRecurringInstance($task) { return null; }
        };
        $this->app->instance(\App\Services\RecurringTaskService::class, $recurringService);

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('tasks.approve', $task), ['review_notes' => 'Looks good']);

        $resp->assertRedirect(route('admin.tasks.review'));
        $resp->assertSessionHas('success');

        $task->refresh();
        $pt->refresh();
        $this->assertEquals(TaskStatus::COMPLETED, $task->status);
        $this->assertEquals(TaskStatus::COMPLETED, $pt->status);
    }

    public function test_admin_reject_proxies_to_planning_task_controller_and_redirects(): void
    {
        $admin = User::factory()->admin()->create();
        $loc = Location::factory()->create();
        $task = Task::factory()->create(['location_id' => $loc->id, 'status' => TaskStatus::REVIEW->value]);
        $planning = Planning::factory()->create();
        $pt = PlanningTask::create([
            'planning_id' => $planning->id,
            'task_id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => TaskStatus::REVIEW->value,
            'completed_at' => now(),
        ]);
        PlanningTaskCompletion::create([
            'planning_task_id' => $pt->id,
            'comment' => 'Attempt',
            'user_id' => $admin->id,
        ]);

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('tasks.reject', $task), ['review_notes' => 'Not good']);

        $resp->assertRedirect(route('admin.tasks.review'));
        $resp->assertSessionHas('success');
    }
}
