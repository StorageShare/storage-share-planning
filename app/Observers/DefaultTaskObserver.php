<?php

namespace App\Observers;

use App\Models\DefaultTask;
use App\Models\Location;

class DefaultTaskObserver
{
    /**
     * Handle the DefaultTask "saved" event.
     */
    public function saved(DefaultTask $defaultTask): void
    {
        if ($defaultTask->isDirty('applies_to_lift_locations') && $defaultTask->applies_to_lift_locations) {
            $locationIds = Location::whereNotNull('lift')->where('lift', '!=', '')->pluck('id');
            $defaultTask->locations()->syncWithoutDetaching($locationIds);
        } elseif ($defaultTask->isDirty('applies_to_lift_locations') && !$defaultTask->applies_to_lift_locations) {
            $locationIds = Location::whereNotNull('lift')->where('lift', '!=', '')->pluck('id');
            $defaultTask->locations()->detach($locationIds);
        }
    }

    /**
     * Handle the DefaultTask "created" event.
     */
    public function created(DefaultTask $defaultTask): void
    {
        //
    }

    /**
     * Handle the DefaultTask "updated" event.
     */
    public function updated(DefaultTask $defaultTask): void
    {
        //
    }

    /**
     * Handle the DefaultTask "deleted" event.
     */
    public function deleted(DefaultTask $defaultTask): void
    {
        //
    }

    /**
     * Handle the DefaultTask "restored" event.
     */
    public function restored(DefaultTask $defaultTask): void
    {
        //
    }

    /**
     * Handle the DefaultTask "force deleted" event.
     */
    public function forceDeleted(DefaultTask $defaultTask): void
    {
        //
    }
}
