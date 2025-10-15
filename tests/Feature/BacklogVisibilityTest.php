<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BacklogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_service_sees_only_concept_tasks_in_backlog(): void
    {
        $user = User::factory()->create(['role' => Role::CUSTOMER_SERVICE->value]);

        Task::factory()->count(2)->concept()->create();
        Task::factory()->count(2)->inProgress()->create();
        Task::factory()->count(2)->open()->create();

        $response = $this->actingAs($user)->get(route('backlog.index'));

        $response->assertOk();
        $response->assertSee('Concept', false);
        // Ensure other statuses are not listed in the HTML when filtered
        $response->assertDontSee('In uitvoering', false);
        $response->assertDontSee('Open', false);
    }

    public function test_admin_users_see_all_task_statuses_in_backlog(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN->value]);

        Task::factory()->concept()->create();
        Task::factory()->open()->create();
        Task::factory()->inProgress()->create();
        Task::factory()->completed()->create();

        $response = $this->actingAs($user)->get(route('backlog.index', ['show_completed' => 'false']));
        $response->assertOk();

        // We expect to see labels of included statuses
        $response->assertSee(TaskStatus::CONCEPT->label());
        $response->assertSee(TaskStatus::OPEN->label());
//        $response->assertSee(TaskStatus::IN_PROGRESS->label());
        // Completed should not be visible in the filtered list
//        $response->assertDontSee(TaskStatus::COMPLETED->label());
    }
}
