<?php

namespace App\Mail;

use App\Models\Location;
use App\Models\Planning;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LocationCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

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
     * Create a new message instance.
     *
     * @param \App\Models\Location $location
     * @param \App\Models\Planning $planning
     * @return void
     */
    public function __construct(Location $location, Planning $planning)
    {
        $this->location = $location;
        $this->planning = $planning;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Locatie afgerond: ' . $this->location->name,
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            markdown: 'emails.location.completed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
} 