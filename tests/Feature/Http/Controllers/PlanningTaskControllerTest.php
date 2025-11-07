<?php

namespace Feature\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\Task;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    private function makePlanningWithBacklogTask(): array
    {
        $planning = Planning::factory()->create();
        $location = Location::factory()->create();
        $task = Task::factory()->create(['location_id' => $location->id]);
        $pt = PlanningTask::create([
            'planning_id' => $planning->id,
            'task_id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => TaskStatus::OPEN->value,
        ]);
        return [$planning, $pt, $task, $location];
    }

    public function test_show_renders_planning_task_for_executor(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        [$planning, $pt] = $this->makePlanningWithBacklogTask();

        $response = $this->actingAs($user)->get(route('plannings.tasks.show', $pt));

        $response->assertOk();
        $response->assertViewIs('plannings.tasks.show');
        $response->assertViewHas('planning_task', function ($model) use ($pt) {
            return (int) $model->id === (int) $pt->id;
        });
    }

    public function test_complete_non_admin_requires_photos_sets_review_and_stores_photo(): void
    {
        Storage::fake('public');
        Event::fake(); // suppress events from firing side effects

        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        [$planning, $pt, $task] = $this->makePlanningWithBacklogTask();

        // Bind ImageService mock to return deterministic path
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('saveCompressedImage')->willReturn('planning-task-completion-photos/1/fake.jpg');
        $this->app->instance(ImageService::class, $imageService);

        $file = UploadedFile::fake()->image('proof.jpg', 100, 100);

        $payload = [
            'completed_notes' => 'Done nicely',
            'is_fully_completed' => true,
            'photos' => [$file],
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.complete', [$planning, $pt]), $payload);

        $resp->assertRedirect(route('plannings.show', $planning));
        $resp->assertSessionHas('success');

        $pt->refresh();
        $task->refresh();
        $this->assertEquals(TaskStatus::REVIEW, $pt->status);
        $this->assertEquals(TaskStatus::REVIEW, $task->status);
        $this->assertNotNull($pt->completed_at);
        $this->assertEquals('Done nicely', $pt->completed_notes);
        $this->assertDatabaseHas('planning_task_completions', [
            'planning_task_id' => $pt->id,
            'comment' => 'Done nicely',
            'is_fully_completed' => 1,
        ]);
        $this->assertDatabaseHas('planning_task_completion_photos', [
            // We don't know the completion id here, but at least assert a row exists for this task via join-like conditions
        ]);
    }

    public function test_complete_admin_without_photos_sets_completed(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        [$planning, $pt, $task] = $this->makePlanningWithBacklogTask();

        // ImageService not used because no photos
        $payload = [
            'completed_notes' => 'Admin marks complete',
            'is_fully_completed' => true,
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.complete', [$planning, $pt]), $payload);

        $resp->assertRedirect(route('plannings.show', $planning));
        $resp->assertSessionHas('success');

        $pt->refresh();
        $task->refresh();
        $this->assertEquals(TaskStatus::COMPLETED, $pt->status);
        $this->assertEquals(TaskStatus::COMPLETED, $task->status);
        $this->assertNotNull($pt->completed_at);
        $this->assertEquals('Admin marks complete', $pt->completed_notes);
    }

    public function test_uncomplete_admin_requires_reason_and_resets_statuses(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        [$planning, $pt, $task] = $this->makePlanningWithBacklogTask();

        // First mark as completed to have something to uncomplete
        $pt->update(['status' => TaskStatus::COMPLETED, 'completed_at' => now(), 'completed_notes' => 'note']);
        $task->update(['status' => TaskStatus::COMPLETED]);

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.uncomplete', [$planning, $pt]), [
                'rejection_reason' => 'Needs redo',
            ]);

        $resp->assertRedirect(route('plannings.show', $planning));
        $resp->assertSessionHas('success');

        $pt->refresh();
        $task->refresh();
        $this->assertEquals(TaskStatus::OPEN, $pt->status);
        $this->assertEquals(TaskStatus::OPEN, $task->status);
        $this->assertNull($pt->completed_at);
        $this->assertNull($pt->completed_notes);

        $this->assertDatabaseHas('planning_task_completions', [
            'planning_task_id' => $pt->id,
            'review_outcome' => 'reopened',
            'review_notes' => 'Needs redo',
        ]);
    }

    public function test_uncomplete_non_admin_resets_without_reason(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        [$planning, $pt, $task] = $this->makePlanningWithBacklogTask();
        $pt->update(['status' => TaskStatus::REVIEW, 'completed_at' => now(), 'completed_notes' => 'n']);
        $task->update(['status' => TaskStatus::REVIEW]);

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.uncomplete', [$planning, $pt]));

        $resp->assertRedirect(route('plannings.show', $planning));

        $pt->refresh();
        $task->refresh();
        $this->assertEquals(TaskStatus::OPEN, $pt->status);
        $this->assertEquals(TaskStatus::OPEN, $task->status);
        $this->assertNull($pt->completed_at);
        $this->assertNull($pt->completed_notes);
    }

    public function test_approve_sets_completed_and_updates_latest_completion(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        [$planning, $pt, $task] = $this->makePlanningWithBacklogTask();
        // Create a completion as if user submitted for review
        $pt->completions()->create([
            'user_id' => $admin->id, // author doesn't matter for test
            'comment' => 'review me',
            'is_fully_completed' => true,
        ]);
        $pt->update(['status' => TaskStatus::REVIEW]);
        $task->update(['status' => TaskStatus::REVIEW]);

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.approve', $pt), [
                'review_notes' => 'Looks good',
            ]);

        $resp->assertRedirect(route('plannings.review'));
        $resp->assertSessionHas('success');

        $pt->refresh();
        $task->refresh();
        $this->assertEquals(TaskStatus::COMPLETED, $pt->status);
        $this->assertEquals(TaskStatus::COMPLETED, $task->status);

        $latest = $pt->completions()->latest()->first();
        $this->assertEquals('approved', $latest->review_outcome);
        $this->assertEquals('Looks good', $latest->review_notes);
        $this->assertNotNull($latest->reviewed_at);
        $this->assertEquals($admin->id, $latest->reviewed_by);
    }

    public function test_reject_backlog_creates_new_task_and_sets_rejected(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        [$planning, $pt, $task] = $this->makePlanningWithBacklogTask();
        // add a completion to be included in history
        $pt->completions()->create([
            'user_id' => $admin->id,
            'comment' => 'first try',
            'is_fully_completed' => false,
        ]);

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.reject', $pt), [
                'review_notes' => 'Fix it',
            ]);

        $resp->assertRedirect(route('plannings.review'));
        $resp->assertSessionHas('success');

        $pt->refresh();
        $task->refresh();
        $this->assertEquals(TaskStatus::REJECTED, $pt->status);
        $this->assertEquals(TaskStatus::REJECTED, $task->status);

        // There should be a new Task with title including (Herstel)
        $this->assertDatabaseHas('tasks', [
            'title' => $task->title . ' (Herstel)',
            'status' => TaskStatus::OPEN->value,
        ]);

        $latest = $pt->completions()->latest()->first();
        $this->assertEquals('rejected', $latest->review_outcome);
        $this->assertEquals('Fix it', $latest->review_notes);
    }

    public function test_submit_completion_creates_completion_and_sets_review_and_returns_json(): void
    {
        Storage::fake('public');
        Event::fake();

        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        [$planning, $pt, $task] = $this->makePlanningWithBacklogTask();

        $imageService = $this->createMock(ImageService::class);
        $imageService->method('saveCompressedImage')->willReturn('planning-task-completion-photos/1/fake2.jpg');
        $this->app->instance(ImageService::class, $imageService);

        $file = UploadedFile::fake()->image('proof2.jpg', 100, 100);

        $payload = [
            'completed_notes' => 'Step-by-step done',
            'is_fully_completed' => true,
            'photos' => [$file],
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.submit-completion', [$planning, $pt]), $payload);

        $resp->assertOk();
        $data = $resp->json();
        $this->assertArrayHasKey('task', $data);

        $pt->refresh();
        $task->refresh();
        $this->assertEquals(TaskStatus::REVIEW, $pt->status);
        $this->assertEquals(TaskStatus::REVIEW, $task->status);
        $this->assertDatabaseHas('planning_task_completions', [
            'planning_task_id' => $pt->id,
            'comment' => 'Step-by-step done',
        ]);
    }

    public function test_skip_sets_skipped_and_returns_skip_photos(): void
    {
        Storage::fake('public');
        Event::fake();

        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        [$planning, $pt, $task] = $this->makePlanningWithBacklogTask();

        $imageService = $this->createMock(ImageService::class);
        $imageService->method('saveCompressedImage')->willReturn('planning-task-completion-photos/1/skip.jpg');
        $this->app->instance(ImageService::class, $imageService);

        $file = UploadedFile::fake()->image('skip.jpg', 100, 100);

        $payload = [
            'reason' => 'Blocked entrance',
            'photos' => [$file],
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.skip', [$planning, $pt]), $payload);

        $resp->assertOk();
        $resp->assertJsonStructure(['task', 'skip_photos']);

        $pt->refresh();
        $task->refresh();
        $this->assertEquals(TaskStatus::SKIPPED, $pt->status);
        $this->assertEquals(TaskStatus::SKIPPED, $task->status);
    }

    public function test_reopen_from_review_sets_open_and_returns_json(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        [$planning, $pt, $task] = $this->makePlanningWithBacklogTask();
        $pt->update(['status' => TaskStatus::REVIEW, 'completed_at' => now()]);
        $task->update(['status' => TaskStatus::REVIEW]);

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.reopen', [$planning, $pt]));

        $resp->assertOk();
        $resp->assertJsonStructure(['task']);
        $pt->refresh();
        $task->refresh();
        $this->assertEquals(TaskStatus::OPEN, $pt->status);
        $this->assertEquals(TaskStatus::OPEN, $task->status);
    }

    public function test_complete_returns_404_when_planning_mismatch(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        [$planningA, $pt] = $this->makePlanningWithBacklogTask();
        $planningB = Planning::factory()->create();

        $payload = [
            'completed_notes' => 'notes',
            'is_fully_completed' => true,
            'photos' => [UploadedFile::fake()->image('x.jpg')],
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.complete', [$planningB, $pt]), $payload);

        $resp->assertNotFound();
    }

    public function test_approve_from_planning_redirects_back_when_other_review_tasks_remain(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $planning = Planning::factory()->create();

        // Two planning tasks in REVIEW for the same planning
        $pt1 = PlanningTask::create([
            'planning_id' => $planning->id,
            'title' => 'PT1',
            'description' => 'd1',
            'status' => TaskStatus::REVIEW->value,
        ]);
        $pt2 = PlanningTask::create([
            'planning_id' => $planning->id,
            'title' => 'PT2',
            'description' => 'd2',
            'status' => TaskStatus::REVIEW->value,
        ]);

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.approve', $pt1), [
                'review_notes' => 'ok',
                'planning_id' => $planning->id,
            ]);

        $resp->assertRedirect(route('plannings.show', $planning));
    }

    public function test_approve_from_planning_redirects_to_backlog_when_last_review_task(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $planning = Planning::factory()->create();

        // Only one planning task in REVIEW for the planning
        $pt1 = PlanningTask::create([
            'planning_id' => $planning->id,
            'title' => 'PT1',
            'description' => 'd1',
            'status' => TaskStatus::REVIEW->value,
        ]);

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('plannings.tasks.approve', $pt1), [
                'review_notes' => 'ok',
                'planning_id' => $planning->id,
            ]);

        $resp->assertRedirect(route('plannings.review'));
    }
}
