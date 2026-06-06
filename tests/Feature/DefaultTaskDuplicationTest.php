<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Planning;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultTaskDuplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_task_is_not_duplicated_when_linked_to_planning()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $defaultTask = DefaultTask::factory()->create([
            'title' => 'Mijn Standaard Taak',
            'applies_to_all_locations' => true,
        ]);
        $location->defaultTasks()->attach($defaultTask);

        $response = $this->actingAs($admin)->post(route('plannings.store'), [
            'planned_date' => now()->addDay()->format('Y-m-d'),
            'start_address_option' => 'Kantoor',
            'start_address' => 'Kantoor',
            'vehicle_id' => $vehicle->id,
            'user_ids' => [$admin->id],
            'location_ids' => [$location->id],
            'selected_default_tasks' => [$defaultTask->id],
        ]);

        $response->assertRedirect();

        $planning = Planning::first();
        $planningTasks = $planning->planningTasks;

        // Er zou maar 1 planning task moeten zijn
        $this->assertCount(1, $planningTasks, "Er zijn meer dan 1 planning tasks aangemaakt voor 1 standaard taak.");

        $planningTask = $planningTasks->first();
        $this->assertNull($planningTask->default_task_id, "De planning taak zou geen default_task_id meer moeten hebben als hij gedupliceerd is naar een task_id.");
        $this->assertNotNull($planningTask->task_id, "De planning taak zou gekoppeld moeten zijn aan een nieuw aangemaakte taak.");

        // Simuleer de filter logica uit de view
        $location = $planning->locations->first();
        $tasksForLocation = $planning->planningTasks->filter(function ($pt) use ($location) {
            if ($pt->task_id && $pt->task) { // Backlog Task
                return $pt->task->location_id == $location->id;
            } elseif ($pt->default_task_id && $pt->defaultTask) { // Default Task
                return $pt->location_id == $location->id;
            }
            return false;
        });

        $this->assertCount(1, $tasksForLocation, "De view filter logica vindt meer dan 1 taak voor de locatie.");
    }
}
