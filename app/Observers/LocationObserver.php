<?php

namespace App\Observers;

use App\Models\Location;
use App\Models\DefaultTask;

class LocationObserver
{
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
