<?php

namespace Tests\Feature;

use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationDuplicateTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_location_with_overlapping_default_tasks_does_not_fail()
    {
        $this->withoutMiddleware();
        $user = User::factory()->create();

        // Maak een default task die op alle locaties van toepassing is EN op een specifiek deurtype
        $defaultTask = DefaultTask::create([
            'title' => 'Overlap Task',
            'description' => 'Overlap Description',
            'applies_to_all_locations' => true,
            'applies_to_door_types' => true,
            'door_types' => ['overhead'],
            'estimated_time_minutes' => 30,
        ]);

        // Probeer een locatie aan te maken met dat deurtype
        // Dit zou de LocationObserver::created triggeren
        $response = $this->actingAs($user)->post(route('locations.store'), [
            'name' => 'Test Location',
            'address' => 'Teststraat 1',
            'postal_code' => '1234 AB',
            'city' => 'Teststad',
            'type_deur' => 'overhead',
            'lift' => false,
        ]);

        $response->assertRedirect(route('locations.index'));
        $this->assertDatabaseHas('locations', ['name' => 'Test Location']);

        $location = Location::where('name', 'Test Location')->first();

        // Controleer of de koppeling bestaat (slechts één keer)
        $this->assertEquals(1, $location->defaultTasks()->where('default_tasks.id', $defaultTask->id)->count());
    }
}
