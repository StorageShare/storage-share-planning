<?php

namespace App\Console;

use App\Console\Commands\EscalateTaskPriorities;
use App\Console\Commands\SyncExternalLocationsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string<\Illuminate\Console\Command>>
     */
    protected $commands = [
        // Commands\SyncExternalLocationsCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command(SyncExternalLocationsCommand::class)->daily();
        $schedule->command(\App\Console\Commands\SendPlanningNotificationsCommand::class)->dailyAt('16:00');
        $schedule->command(EscalateTaskPriorities::class, ['--force'])
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
