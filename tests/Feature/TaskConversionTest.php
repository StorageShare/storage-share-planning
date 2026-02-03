<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\ExternalTask;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_convert_task_to_external_task(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $location = Location::factory()->create();
        $task = Task::factory()->create([
            'location_id' => $location->id,
            'title' => 'Task to Convert',
            'description' => 'Original Description',
            'deadline' => now()->addDays(7)->startOfDay(),
            'estimated_time_minutes' => 60,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('tasks.convert-to-external', $task));

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);

        $externalTask = ExternalTask::first();
        $this->assertNotNull($externalTask);
        $this->assertEquals($task->title, $externalTask->title);
        $this->assertEquals($task->description, $externalTask->description);
        $this->assertEquals($task->location_id, $externalTask->location_id);
        $this->assertEquals($task->deadline->format('Y-m-d'), $externalTask->external_deadline_at->format('Y-m-d'));
        $this->assertEquals($task->estimated_time_minutes, $externalTask->estimated_time_minutes);
        $this->assertEquals(TaskStatus::REVIEW, $externalTask->status);

        $response->assertRedirect(route('external-backlog.show', $externalTask));
        $response->assertSessionHas('success', 'Taak is succesvol omgezet naar een externe taak.');
    }

    public function test_non_admin_cannot_convert_task_to_external_task(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER]);
        $task = Task::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('tasks.convert-to-external', $task));

        $response->assertStatus(403);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseCount('external_tasks', 0);
    }
}
