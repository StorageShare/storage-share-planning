<?php

namespace App\Providers;

use App\Events\LocationCompleted;
use App\Events\TaskReadyForReview;
use App\Listeners\SendLocationCompletedNotification;
use App\Listeners\SendTaskReviewNotification;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Task;
use App\Observers\DefaultTaskObserver;
use App\Observers\LocationObserver;
use App\Observers\TaskObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TaskReadyForReview::class => [
            SendTaskReviewNotification::class,
        ],
        //        LocationCompleted::class => [
        //            SendLocationCompletedNotification::class,
        //        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Location::observe(LocationObserver::class);
        DefaultTask::observe(DefaultTaskObserver::class);
        Task::observe(TaskObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
