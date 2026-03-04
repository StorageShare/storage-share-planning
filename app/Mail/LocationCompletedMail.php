<?php

namespace App\Mail;

use App\Models\Location;
use App\Models\Planning;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LocationCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The location instance.
     *
     * @var Location
     */
    public $location;

    /**
     * The planning instance.
     *
     * @var Planning
     */
    public $planning;

    /**
     * Create a new message instance.
     *
     * @param Location $location
     * @param Planning $planning
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
     * @return Envelope
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
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.location.completed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
