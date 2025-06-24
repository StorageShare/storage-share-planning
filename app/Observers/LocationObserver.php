<?php

namespace App\Observers;

use App\Models\Location;
use App\Models\DefaultTask;
use App\Models\Task;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Illuminate\Support\Facades\Auth;

class LocationObserver
{
    /**
     * Handle the Location "saved" event.
     */
    public function saved(Location $location): void
    {
        if ($location->isDirty('lift') && !empty($location->lift)) {
            $taskIds = DefaultTask::where('applies_to_lift_locations', true)->pluck('id');
            $location->defaultTasks()->syncWithoutDetaching($taskIds);
        } elseif ($location->isDirty('lift') && empty($location->lift)) {
            $taskIds = DefaultTask::where('applies_to_lift_locations', true)->pluck('id');
            $location->defaultTasks()->detach($taskIds);
        }
    }

    /**
     * Handle the Location "created" event.
     */
    public function created(Location $location): void
    {
        // Zoek alle default tasks die van toepassing zijn op alle locaties
        $defaultTasksForAllLocations = DefaultTask::where('applies_to_all_locations', true)->get();
        
        // Koppel de nieuwe locatie aan deze default tasks
        foreach ($defaultTasksForAllLocations as $defaultTask) {
            $defaultTask->locations()->attach($location->id);
        }

        // Zoek default tasks die van toepassing zijn op het deur type van deze locatie
        if (!empty($location->type_deur)) {
            $defaultTasksForDoorType = DefaultTask::where('applies_to_door_types', true)
                ->whereNotNull('door_types')
                ->get()
                ->filter(function ($defaultTask) use ($location) {
                    return $defaultTask->appliesToLocationByDoorType($location);
                });

            // Koppel de nieuwe locatie aan deze default tasks (vermijd duplicaten)
            foreach ($defaultTasksForDoorType as $defaultTask) {
                if (!$defaultTask->locations()->where('location_id', $location->id)->exists()) {
                    $defaultTask->locations()->attach($location->id);
                }
            }
        }

        // Maak automatisch Schoonmaken en Controleronde taken aan
        $this->createDefaultTasksForLocation($location);
    }

    /**
     * Create default Schoonmaken and Controleronde tasks for a new location.
     */
    private function createDefaultTasksForLocation(Location $location): void
    {
        $userId = Auth::id() ?? 1; // Fallback naar user ID 1 als er geen ingelogde gebruiker is

        // Schoonmaken taak
        $schoonmakenTask = Task::create([
            'location_id' => $location->id,
            'title' => 'Schoonmaken',
            'description' => 'Vloer schrobben met schrobmachine + schoonmaken bezem/veger/blik en alle andere dingen die niet schoon zijn!',
            'priority' => TaskPriority::NORMAL,
            'status' => TaskStatus::OPEN,
            'created_by' => $userId,
            'deadline' => now()->addMonths(3),
            'is_recurring' => true,
            'recurring_interval_type' => 'months',
            'recurring_interval_value' => 3,
            'estimated_minutes' => 240, // 4 uur
        ]);

        // Controleronde taak
        $controlerondeTask = Task::create([
            'location_id' => $location->id,
            'title' => 'Controleronde',
            'description' => 'Voer een controleronde uit en noteer alle bijzonderheden en voeg voor elke bijzonderheid foto\'s toe!',
            'priority' => TaskPriority::NORMAL,
            'status' => TaskStatus::OPEN,
            'created_by' => $userId,
            'deadline' => now()->addMonths(6),
            'is_recurring' => true,
            'recurring_interval_type' => 'months',
            'recurring_interval_value' => 6,
            'estimated_minutes' => 240, // 4 uur
        ]);
    }

    /**
     * Handle the Location "updated" event.
     */
    public function updated(Location $location): void
    {
        // Check if the door type was changed
        if ($location->isDirty('type_deur')) {
            // Remove location from door type based default tasks that no longer apply
            $doorTypeBasedTasks = DefaultTask::where('applies_to_door_types', true)
                ->whereNotNull('door_types')
                ->get();

            foreach ($doorTypeBasedTasks as $defaultTask) {
                $currentlyLinked = $defaultTask->locations()->where('location_id', $location->id)->exists();
                $shouldBeLinked = $defaultTask->appliesToLocationByDoorType($location);

                if ($currentlyLinked && !$shouldBeLinked) {
                    // Remove the link
                    $defaultTask->locations()->detach($location->id);
                } elseif (!$currentlyLinked && $shouldBeLinked) {
                    // Add the link
                    $defaultTask->locations()->attach($location->id);
                }
            }
        }
    }

    /**
     * Handle the Location "deleted" event.
     */
    public function deleted(Location $location): void
    {
        //
    }

    /**
     * Handle the Location "restored" event.
     */
    public function restored(Location $location): void
    {
        //
    }

    /**
     * Handle the Location "force deleted" event.
     */
    public function forceDeleted(Location $location): void
    {
        //
    }
}
