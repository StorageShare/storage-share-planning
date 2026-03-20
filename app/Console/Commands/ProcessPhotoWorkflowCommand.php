<?php

namespace App\Console\Commands;

use App\Mail\InternalCheckRequestMail;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ProcessPhotoWorkflowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-photo-workflow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verstuur automatische controle mails voor het foto-rondstuur proces.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starten van foto workflow verwerking...');

        // 1. Check voor taken waar een foto is rondgestuurd (1 week geleden)
        $this->processPhotoDistributed();

        // 2. Check voor taken waar een sticker is geplakt (2 weken geleden)
        // Let op: We moeten weten wanneer de sticker taak is AFGEROND.
        $this->processStickerTaskCompleted();

        // 3. Check voor taken waar een nieuwe foto is gemaakt (1 week geleden)
        $this->processSecondPhotoDistributed();

        $this->info('Foto workflow verwerking voltooid.');
    }

    /**
     * 1 week na het rondsturen van de foto een mail inplannen.
     */
    private function processPhotoDistributed()
    {
        $tasks = Task::where('photo_process_step', 'PHOTO_DISTRIBUTED')
            ->where('photo_process_at', '<=', now()->subWeek())
            ->get();

        foreach ($tasks as $task) {
            Mail::to('huur@storage-share.nl')->send(new InternalCheckRequestMail($task, 'STRIKKER_CHECK'));

            $task->update([
                'photo_process_step' => 'PHOTO_DISTRIBUTED_MAIL_SENT',
                'photo_process_at' => now(),
            ]);

            $this->info("Mail gestuurd voor STRIKKER_CHECK: Ruimte {$task->room} op {$task->location->name}");
        }
    }

    /**
     * 2 weken na het afronden van de taak van het plakken van de sticker.
     */
    private function processStickerTaskCompleted()
    {
        // We zoeken naar de STRIKKER_TASK_CREATED proces stap.
        // Maar we moeten weten wanneer de taak op COMPLETED is gezet.
        // We zoeken taken met die status die 2 weken geleden zijn voltooid.
        $tasks = Task::where('photo_process_step', 'STICKER_TASK_CREATED')
            ->where('status', \App\Enums\TaskStatus::COMPLETED)
            ->where('updated_at', '<=', now()->subWeeks(2))
            ->get();

        foreach ($tasks as $task) {
            Mail::to('huur@storage-share.nl')->send(new InternalCheckRequestMail($task, 'SECOND_PHOTO_CHECK'));

            $task->update([
                'photo_process_step' => 'STICKER_COMPLETED_MAIL_SENT',
                'photo_process_at' => now(),
            ]);

            $this->info("Mail gestuurd voor SECOND_PHOTO_CHECK: Ruimte {$task->room} op {$task->location->name}");
        }
    }

    /**
     * Een week na het rondsturen van de nieuwe foto.
     */
    private function processSecondPhotoDistributed()
    {
        // Na de tweede foto taak voltooiing (SECOND_PHOTO_TASK_CREATED + status COMPLETED)
        $tasks = Task::where('photo_process_step', 'SECOND_PHOTO_TASK_CREATED')
            ->where('status', \App\Enums\TaskStatus::COMPLETED)
            ->where('updated_at', '<=', now()->subWeek())
            ->get();

        foreach ($tasks as $task) {
            Mail::to('huur@storage-share.nl')->send(new InternalCheckRequestMail($task, 'EVACUATION_CHECK'));

            $task->update([
                'photo_process_step' => 'SECOND_PHOTO_COMPLETED_MAIL_SENT',
                'photo_process_at' => now(),
            ]);

            $this->info("Mail gestuurd voor EVACUATION_CHECK: Ruimte {$task->room} op {$task->location->name}");
        }
    }
}
