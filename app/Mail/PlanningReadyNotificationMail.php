<?php

namespace App\Mail;

use App\Models\Planning;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlanningReadyNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The planning instance.
     *
     * @var \App\Models\Planning
     */
    public $planning;

    /**
     * Create a new message instance.
     *
     * @param \App\Models\Planning $planning
     * @return void
     */
    public function __construct(Planning $planning)
    {
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
            subject: 'Planning klaar voor ' . $this->planning->planned_date->format('d-m-Y'),
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
            markdown: 'emails.planning.ready-notification',
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
