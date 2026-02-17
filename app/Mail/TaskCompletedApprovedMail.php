<?php

namespace App\Mail;

use App\Models\PlanningTask;
use App\Models\PlanningTaskCompletion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class TaskCompletedApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public PlanningTask $planningTask,
        public PlanningTaskCompletion $completion
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Taak afgerond: ' . $this->planningTask->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.task-completed-approved',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Collect unique paths to avoid duplicate attachments
        $addedPaths = [];

        // 1) Photos added on the latest completion (preferred)
        try {
            $completionPhotos = $this->completion->photos()->get();
            foreach ($completionPhotos as $photo) {
                $path = (string) ($photo->path ?? '');
                if ($path === '' || isset($addedPaths[$path])) {
                    continue;
                }
                $addedPaths[$path] = true;

                $attachment = Attachment::fromStorageDisk('public', $path);
                if (!empty($photo->original_name)) {
                    $attachment = $attachment->as($photo->original_name);
                }
                if (!empty($photo->mime_type)) {
                    $attachment = $attachment->withMime($photo->mime_type);
                }
                $attachments[] = $attachment;
            }
        } catch (\Throwable $e) {
            // Fail silently – attachments are optional
        }

        // 2) Any photos linked directly to the planning task (fallback / additional)
        try {
            $taskPhotos = $this->planningTask->planningTaskPhotos()->get();
            foreach ($taskPhotos as $photo) {
                $path = (string) ($photo->path ?? '');
                if ($path === '' || isset($addedPaths[$path])) {
                    continue;
                }
                $addedPaths[$path] = true;

                $attachment = Attachment::fromStorageDisk('public', $path);
                if (!empty($photo->original_name)) {
                    $attachment = $attachment->as($photo->original_name);
                }
                if (!empty($photo->mime_type)) {
                    $attachment = $attachment->withMime($photo->mime_type);
                }
                $attachments[] = $attachment;
            }
        } catch (\Throwable $e) {
            // Fail silently – attachments are optional
        }

        return $attachments;
    }
}
