<?php

namespace App\Console\Commands;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EscalateTaskPriorities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:escalate-priorities 
                            {--dry-run : Toon welke taken zouden worden geüpdatet zonder daadwerkelijk te updaten}
                            {--force : Forceer escalatie zonder bevestiging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verhoog automatisch de prioriteit van taken in de backlog. Laag naar Normaal na 60 dagen, Normaal naar Hoog na 30 dagen.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starten met escalatie van taak prioriteiten...');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        try {
            // Get all backlog tasks
            $backlogTasks = $this->getBacklogTasks();

            if ($backlogTasks->isEmpty()) {
                $this->info('✅ Geen taken gevonden in de backlog die escalatie behoeven.');

                return 0;
            }

            $this->info("📋 Gevonden {$backlogTasks->count()} taken in de backlog");

            // Categorize tasks for escalation
            $tasksToEscalate = $this->categorizeTasks($backlogTasks);

            if (empty($tasksToEscalate['low_to_normal']) && empty($tasksToEscalate['normal_to_high'])) {
                $this->info('✅ Geen taken vereisen prioriteit escalatie op dit moment.');

                return 0;
            }

            // Show what will be escalated
            $this->showEscalationPlan($tasksToEscalate);

            if ($dryRun) {
                $this->info('🔍 Dry-run modus: geen wijzigingen doorgevoerd.');

                return 0;
            }

            // Ask for confirmation unless forced
            if (! $force && ! $this->confirm('Wilt u doorgaan met de prioriteit escalatie?')) {
                $this->info('⏹️  Escalatie geannuleerd.');

                return 0;
            }

            // Perform escalation
            $results = $this->performEscalation($tasksToEscalate);

            // Show results
            $this->showResults($results);

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Fout tijdens escalatie: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return 1;
        }
    }

    /**
     * Get all tasks that are in the backlog.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Task>
     */
    private function getBacklogTasks(): \Illuminate\Database\Eloquent\Collection
    {
        return Task::query()
            ->whereIn('status', [TaskStatus::OPEN, TaskStatus::IN_PROGRESS, TaskStatus::REJECTED])
            ->whereDoesntHave('planningTasks') // Not assigned to any planning
            ->with('location')
            ->get();
    }

    /**
     * Categorize tasks that need escalation.
     *
     * @param  iterable<Task>  $tasks
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    private function categorizeTasks(iterable $tasks): array
    {
        $lowToNormal = collect();
        $normalToHigh = collect();

        foreach ($tasks as $task) {
            $daysInCurrentPriority = $this->getDaysInCurrentPriority($task);

            if ($task->priority === TaskPriority::LOW && $daysInCurrentPriority >= 60) {
                $lowToNormal->push([
                    'task' => $task,
                    'days' => $daysInCurrentPriority,
                ]);
            } elseif ($task->priority === TaskPriority::NORMAL && $daysInCurrentPriority >= 30) {
                $normalToHigh->push([
                    'task' => $task,
                    'days' => $daysInCurrentPriority,
                ]);
            }
        }

        return [
            'low_to_normal' => $lowToNormal,
            'normal_to_high' => $normalToHigh,
        ];
    }

    /**
     * Calculate days in current priority.
     */
    private function getDaysInCurrentPriority(Task $task): int
    {
        // If priority_updated_at exists, use that, otherwise fall back to created_at
        $referenceDate = $task->priority_updated_at ?: $task->created_at;

        return (int) Carbon::parse($referenceDate)->diffInDays(now());
    }

    /**
     * Show the escalation plan.
     *
     * @param  array<string, Collection<int, array<string, mixed>>>  $tasksToEscalate
     */
    private function showEscalationPlan(array $tasksToEscalate): void
    {
        if (! empty($tasksToEscalate['low_to_normal'])) {
            $this->newLine();
            $this->info('📈 Taken die van LAAG naar NORMAAL gaan (60+ dagen):');
            $this->table(
                ['ID', 'Titel', 'Locatie', 'Dagen in huidige prioriteit', 'Aangemaakt'],
                $tasksToEscalate['low_to_normal']->map(function ($item) {
                    return [
                        $item['task']->id,
                        Str::limit($item['task']->title, 40),
                        $item['task']->location->name ?? 'Onbekend',
                        $item['days'],
                        $item['task']->created_at->format('d-m-Y'),
                    ];
                })
            );
        }

        if (! empty($tasksToEscalate['normal_to_high'])) {
            $this->newLine();
            $this->info('🔴 Taken die van NORMAAL naar HOOG gaan (30+ dagen):');
            $this->table(
                ['ID', 'Titel', 'Locatie', 'Dagen in huidige prioriteit', 'Aangemaakt'],
                $tasksToEscalate['normal_to_high']->map(function ($item) {
                    return [
                        $item['task']->id,
                        Str::limit($item['task']->title, 40),
                        $item['task']->location->name ?? 'Onbekend',
                        $item['days'],
                        $item['task']->created_at->format('d-m-Y'),
                    ];
                })
            );
        }
    }

    /**
     * Perform the actual escalation.
     *
     * @param  array<string, Collection<int, array<string, mixed>>>  $tasksToEscalate
     * @return array<string, mixed>
     */
    private function performEscalation(array $tasksToEscalate): array
    {
        $results = [
            'low_to_normal' => 0,
            'normal_to_high' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($tasksToEscalate, &$results) {
            // Escalate low to normal
            foreach ($tasksToEscalate['low_to_normal'] as $item) {
                try {
                    $task = $item['task'];
                    $task->update([
                        'priority' => TaskPriority::NORMAL,
                        'priority_updated_at' => now(),
                    ]);
                    $results['low_to_normal']++;

                    $this->line("✅ Taak #{$task->id} '{$task->title}' geëscaleerd van LAAG naar NORMAAL");
                } catch (\Exception $e) {
                    $results['errors'][] = "Fout bij escaleren taak #{$item['task']->id}: ".$e->getMessage();
                }
            }

            // Escalate normal to high
            foreach ($tasksToEscalate['normal_to_high'] as $item) {
                try {
                    $task = $item['task'];
                    $task->update([
                        'priority' => TaskPriority::HIGH,
                        'priority_updated_at' => now(),
                    ]);
                    $results['normal_to_high']++;

                    $this->line("✅ Taak #{$task->id} '{$task->title}' geëscaleerd van NORMAAL naar HOOG");
                } catch (\Exception $e) {
                    $results['errors'][] = "Fout bij escaleren taak #{$item['task']->id}: ".$e->getMessage();
                }
            }
        });

        return $results;
    }

    /**
     * Show the results of the escalation.
     *
     * @param  array<string, mixed>  $results
     */
    private function showResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 Escalatie resultaten:');

        $this->table(
            ['Type', 'Aantal'],
            [
                ['LAAG → NORMAAL', $results['low_to_normal']],
                ['NORMAAL → HOOG', $results['normal_to_high']],
                ['Totaal geëscaleerd', $results['low_to_normal'] + $results['normal_to_high']],
                ['Fouten', count($results['errors'])],
            ]
        );

        if (! empty($results['errors'])) {
            $this->newLine();
            $this->error('⚠️  Fouten tijdens escalatie:');
            foreach ($results['errors'] as $error) {
                $this->error("  - {$error}");
            }
        } else {
            $this->newLine();
            $this->info('🎉 Alle escalaties succesvol voltooid!');
        }
    }
}
