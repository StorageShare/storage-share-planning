<?php

namespace App\Events;

use App\Models\Location;
use App\Models\Planning;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The location instance.
     *
     * @var \App\Models\Location
     */
    public $location;

    /**
     * The planning instance.
     *
     * @var \App\Models\Planning
     */
    public $planning;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Location $location, Planning $planning)
    {
        $this->location = $location;
        $this->planning = $planning;
    }
}
