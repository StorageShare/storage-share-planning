<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Models\Location;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
    }

    public function test_bulk_create_renders(): void
    {
        $response = $this->actingAs($this->admin)->get(route('tasks.bulk-create'));

        $response->assertStatus(200);
        $response->assertViewIs('tasks.bulk-create');
    }

    public function test_bulk_store_creates_tasks_for_all_locations(): void
    {
        Location::factory()->count(3)->create();
        $requirement = Requirement::factory()->create();

        $data = [
            'title' => 'Bulk Task',
            'description' => 'Bulk Description',
            'applies_to_all_locations' => '1',
            'requirements' => [$requirement->id],
            'priority' => TaskPriority::HIGH->value,
        ];

        $response = $this->actingAs($this->admin)->post(route('tasks.bulk-store'), $data);

        $response->assertRedirect(route('backlog.index'));
        $this->assertEquals(3, Task::where('title', 'Bulk Task')->count());

        $task = Task::where('title', 'Bulk Task')->first();
        $this->assertTrue($task->requirements->contains($requirement));
        $this->assertEquals(TaskPriority::HIGH, $task->priority);
    }

    public function test_bulk_store_creates_tasks_for_specific_locations(): void
    {
        $locations = Location::factory()->count(5)->create();
        $selectedIds = $locations->take(2)->pluck('id')->toArray();

        $data = [
            'title' => 'Specific Bulk Task',
            'description' => 'Description',
            'locations' => $selectedIds,
        ];

        $response = $this->actingAs($this->admin)->post(route('tasks.bulk-store'), $data);

        $response->assertRedirect(route('backlog.index'));
        $this->assertEquals(2, Task::where('title', 'Specific Bulk Task')->count());
        foreach ($selectedIds as $id) {
            $this->assertTrue(Task::where('title', 'Specific Bulk Task')->where('location_id', $id)->exists());
        }
    }

    public function test_bulk_store_creates_tasks_for_lift_locations(): void
    {
        Location::factory()->count(2)->create(['lift' => 'Ja']);
        Location::factory()->count(3)->create(['lift' => 'Nee']);

        $data = [
            'title' => 'Lift Task',
            'description' => 'Description',
            'applies_to_lift_locations' => '1',
        ];

        $response = $this->actingAs($this->admin)->post(route('tasks.bulk-store'), $data);

        $this->assertEquals(2, Task::where('title', 'Lift Task')->count());
    }

    public function test_bulk_store_creates_tasks_for_door_types(): void
    {
        Location::factory()->create(['type_deur' => 'Overhead']);
        Location::factory()->create(['type_deur' => 'overhead ']); // Case and space insensitive
        Location::factory()->create(['type_deur' => 'Roldeur']);

        $data = [
            'title' => 'Door Task',
            'description' => 'Description',
            'applies_to_door_types' => '1',
            'door_types' => ['overhead'],
        ];

        $response = $this->actingAs($this->admin)->post(route('tasks.bulk-store'), $data);

        $this->assertEquals(2, Task::where('title', 'Door Task')->count());
    }

    public function test_bulk_store_fails_if_no_locations_found(): void
    {
        $data = [
            'title' => 'No Loc Task',
            'description' => 'Description',
            'locations' => [], // Empty
        ];

        $response = $this->actingAs($this->admin)->post(route('tasks.bulk-store'), $data);

        $response->assertSessionHas('error');
        $this->assertEquals(0, Task::where('title', 'No Loc Task')->count());
    }
}
