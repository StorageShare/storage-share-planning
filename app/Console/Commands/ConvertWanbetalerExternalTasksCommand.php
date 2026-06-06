<?php

namespace App\Console\Commands;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ExternalTask;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertWanbetalerExternalTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:convert-wanbetaler-external
                            {--title=[wanbetaler] : Substring the external task title must contain}
                            {--since= : Only convert external tasks created on/after this date (Y-m-d)}
                            {--dry-run : Only show what would be converted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Zet wanbetaler external tasks om naar normale taken en verwijder de external tasks.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $title = (string) $this->option('title');

        $query = ExternalTask::query()->where('title', 'like', '%'.$title.'%');

        if ($since = $this->option('since')) {
            $query->whereDate('created_at', '>=', $since);
        }

        $externalTasks = $query->orderBy('id')->get();

        if ($externalTasks->isEmpty()) {
            $this->warn('Geen external tasks gevonden om om te zetten.');

            return self::SUCCESS;
        }

        $this->info("Gevonden: {$externalTasks->count()} external task(s) met titel die \"{$title}\" bevat.");

        foreach ($externalTasks as $externalTask) {
            $this->line("  #{$externalTask->id} | locatie {$externalTask->location_id} | {$externalTask->title}");
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry-run modus — er is niets gewijzigd.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Deze external tasks omzetten naar normale taken en daarna verwijderen?')) {
            $this->info('Geannuleerd.');

            return self::SUCCESS;
        }

        $converted = 0;

        foreach ($externalTasks as $externalTask) {
            DB::transaction(function () use ($externalTask, &$converted) {
                Task::create([
                    'location_id' => $externalTask->location_id,
                    'title' => $externalTask->title,
                    'description' => $externalTask->description ?? '',
                    'feedback_information' => $externalTask->feedback_information,
                    'feedback_owner_name' => $externalTask->feedback_owner_name,
                    'feedback_emails' => $externalTask->feedback_emails,
                    'deadline' => $externalTask->external_deadline_at,
                    'estimated_time_minutes' => $externalTask->estimated_time_minutes,
                    'priority' => ($externalTask->priority ?? TaskPriority::NORMAL)->value,
                    'status' => TaskStatus::REVIEW,
                ]);

                $externalTask->comments()->delete();
                $externalTask->delete();

                $converted++;
            });
        }

        $this->info("Klaar: {$converted} external task(s) omgezet naar normale taken en verwijderd.");

        return self::SUCCESS;
    }
}
