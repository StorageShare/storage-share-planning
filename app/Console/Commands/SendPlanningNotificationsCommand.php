<?php

namespace App\Console\Commands;

use App\Mail\PlanningReadyNotificationMail;
use App\Models\Planning;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow();
        $this->info("Zoeken naar planningen voor morgen: " . $tomorrow->toDateString());

        $plannings = Planning::whereDate('planned_date', $tomorrow)
            ->with(['users'])
            ->get();

        if ($plannings->isEmpty()) {
            $this->info("Geen planningen gevonden voor morgen.");
            return Command::SUCCESS;
        }

        $totalSent = 0;

        foreach ($plannings as $planning) {
            if ($planning->users->isEmpty()) {
                $this->warn("Planning #{$planning->id} heeft geen toegewezen gebruikers. Overslaan.");
                continue;
            }

            foreach ($planning->users as $user) {
                try {
                    Mail::to($user->email)->send(new PlanningReadyNotificationMail($planning));
                    $totalSent++;
                    $this->info("Notificatie verstuurd naar {$user->email} voor planning #{$planning->id}");
                } catch (\Exception $e) {
                    $errorMessage = "Fout bij het versturen van notificatie naar user #{$user->id} voor planning #{$planning->id}: " . $e->getMessage();
                    $this->error($errorMessage);
                    Log::error($errorMessage);
                }
            }
        }

        $this->info("Klaar! Totaal aantal verstuurde notificaties: {$totalSent}");

        return Command::SUCCESS;
    }
}
