<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Planning;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BacklogInReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_backlog_shows_in_review_tasks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inReviewTask = Task::factory()->create([
            'title' => 'In Review Task',
            'status' => TaskStatus::IN_REVIEW,
        ]);

        $response = $this->actingAs($admin)->get('/backlog');

        $response->assertStatus(200);
        $response->assertSee('In Review Task');
    }

    public function test_planning_create_shows_in_review_tasks_in_backlog_selection(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $location = Location::factory()->create();
        $inReviewTask = Task::factory()->create([
            'title' => 'In Review Task for Planning',
            'status' => TaskStatus::IN_REVIEW,
            'location_id' => $location->id,
        ]);

        $response = $this->actingAs($admin)->get(route('plannings.create'));

        $response->assertStatus(200);
        $response->assertSee('In Review Task for Planning');
    }

    public function test_planning_edit_shows_in_review_tasks_in_backlog_selection(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $location = Location::factory()->create();
        $planning = Planning::factory()->create();
        $planning->locations()->attach($location);

        $inReviewTask = Task::factory()->create([
            'title' => 'In Review Task for Planning Edit',
            'status' => TaskStatus::IN_REVIEW,
            'location_id' => $location->id,
        ]);

        $response = $this->actingAs($admin)->get(route('plannings.edit', $planning));

        $response->assertStatus(200);
        $response->assertSee('In Review Task for Planning Edit');
    }
}
