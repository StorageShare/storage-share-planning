<?php

namespace App\Listeners;

use App\Enums\Role;
use App\Events\TaskReadyForReview;
use App\Mail\TaskReadyForReviewMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SendTaskReviewNotification
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
     * @return void
     */
    public function handle(TaskReadyForReview $event)
    {
        $admins = User::where('role', Role::ADMIN->value)->get();

        foreach ($admins as $admin) {
            Mail::to($admin)->send(new TaskReadyForReviewMail($event->task));
        }
    }
}
