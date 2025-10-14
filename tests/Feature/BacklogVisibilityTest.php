<?php

namespace Tests\Feature;

use App\Enums\Role;
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
        Task::factory()->count(2)->create(['status' => 'open']);

        $response = $this->actingAs($user)->get(route('backlog.index'));

        $response->assertOk();
        $response->assertSee('Concept', false);
        // Ensure other statuses are not listed in the HTML when filtered
        $response->assertDontSee('In uitvoering', false);
        $response->assertDontSee('Open', false);
    }

    public function test_other_users_see_concept_open_and_in_progress_when_not_show_completed(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN->value]);

        $conceptTasks = Task::factory()->count(1)->concept()->create();
        $openTasks = Task::factory()->count(1)->create(['status' => 'open']);
        $inProgressTasks = Task::factory()->count(1)->inProgress()->create();
        $completedTasks = Task::factory()->count(1)->completed()->create();

        $response = $this->actingAs($user)->get(route('backlog.index', ['show_completed' => 'false']));
        $response->assertOk();

        // We expect to see labels of included statuses
        $response->assertSee('Concept', false);
        $response->assertSee('Open', false);
        $response->assertSee('In uitvoering', false);
        // Completed should not be visible in the filtered list
        $response->assertDontSee('Voltooid', false);
    }
}
