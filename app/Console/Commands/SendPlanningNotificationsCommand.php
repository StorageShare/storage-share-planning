<?php

namespace App\Console\Commands;

use App\Services\PlanningNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendPlanningNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-planning-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send planning ready notification emails for the next day';

    public function __construct(
        private readonly PlanningNotificationService $planningNotificationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow();
        $this->info('Zoeken naar planningen voor morgen met openstaande notificaties: '.$tomorrow->toDateString());

        $plannings = $this->planningNotificationService->planningsWithPendingNotificationsForDate($tomorrow);

        if ($plannings->isEmpty()) {
            $this->info('Geen openstaande notificaties voor morgen.');

            return Command::SUCCESS;
        }

        $totalSent = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        foreach ($plannings as $planning) {
            if ($planning->users->isEmpty()) {
                $this->warn("Planning #{$planning->id} heeft geen gebruikers met openstaande notificaties. Overslaan.");

                continue;
            }

            $stats = $this->planningNotificationService->sendPendingForPlanning($planning);
            $totalSent += $stats['sent'];
            $totalSkipped += $stats['skipped'];
            $totalFailed += $stats['failed'];

            if ($stats['sent'] > 0) {
                $this->info("Planning #{$planning->id}: {$stats['sent']} notificatie(s) verstuurd.");
            }
        }

        $this->info("Klaar! Verstuurd: {$totalSent}, overgeslagen (al verstuurd): {$totalSkipped}, mislukt: {$totalFailed}.");

        return Command::SUCCESS;
    }
}
