<?php

namespace App\Listeners;

use App\Events\LocationCompleted;
use App\Mail\LocationCompletedMail;
use Illuminate\Support\Facades\Mail;

class SendLocationCompletedNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param \App\Events\LocationCompleted $event
     * @return void
     */
    public function handle(LocationCompleted $event)
    {
        // Verstuur mail naar facilitair@storage-share.nl
        Mail::to('facilitair@storage-share.nl')->send(
            new LocationCompletedMail($event->location, $event->planning)
        );
    }
} 