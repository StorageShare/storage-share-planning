<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBacklogBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
    }

    public function test_admin_can_bulk_delete_selected_tasks(): void
    {
        $loc = Location::factory()->create();
        $t1 = Task::factory()->create(['location_id' => $loc->id]);
        $t2 = Task::factory()->create(['location_id' => $loc->id]);
        $t3 = Task::factory()->create(['location_id' => $loc->id]);

        $payload = [
            'task_ids' => [$t1->id, $t3->id],
        ];

        $resp = $this->actingAs($this->admin)->delete(route('backlog.bulk-destroy'), $payload);

        $resp->assertStatus(302);
        $resp->assertSessionHas('success');
        $this->assertDatabaseMissing('tasks', ['id' => $t1->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $t3->id]);
        $this->assertDatabaseHas('tasks', ['id' => $t2->id]);
    }

    public function test_bulk_delete_requires_at_least_one_selection(): void
    {
        $resp = $this->actingAs($this->admin)->delete(route('backlog.bulk-destroy'), [
            'task_ids' => [],
        ]);

        $resp->assertStatus(302);
        $resp->assertSessionHasErrors(['task_ids']);
    }
}
