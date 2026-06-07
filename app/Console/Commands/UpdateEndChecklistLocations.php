<?php

namespace App\Console\Commands;

use App\Models\EndChecklistItem;
use App\Models\PlanningTask;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class UpdateEndChecklistLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checklist:update-locations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing end checklist items with location information';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Updating end checklist items with location information...');

        $items = EndChecklistItem::with([
            'requirement',
            'planning.planningTasks.task',
            'planning.planningTasks.defaultTask',
            'planning.planningTasks.specificLocation',
        ])->get();

        $updated = 0;

        foreach ($items as $item) {
            if ($item->type === 'material' && $item->requirement) {
                // Find location through planning tasks
                /** @var Collection<int, PlanningTask> $planningTasks */
                $planningTasks = $item->planning->planningTasks;
                $planningTask = $planningTasks
                    ->filter(function ($pt) use ($item) {
                        return ($pt->task && $pt->task->requirements->contains($item->requirement_id)) ||
                               ($pt->defaultTask && $pt->defaultTask->requirements->contains($item->requirement_id));
                    })
                    ->first();

                if ($planningTask) {
                    // Prefer specific location id when present, else fall back to task->location_id
                    $locationId = $planningTask->specificLocation?->id ?: $planningTask->task?->location_id;
                    if ($locationId) {
                        $item->update(['location_id' => $locationId]);
                        $this->line("Updated material item {$item->id} with location {$locationId}");
                        $updated++;
                    }
                }
            } elseif ($item->type === 'end_action') {
                // Find location through planning tasks with end_day_action
                /** @var Collection<int, PlanningTask> $planningTasks */
                $planningTasks = $item->planning->planningTasks;
                $planningTask = $planningTasks
                    ->filter(function ($pt) use ($item) {
                        return ($pt->task && $pt->task->end_day_action_title === $item->title) ||
                               ($pt->defaultTask && $pt->defaultTask->end_day_action_title === $item->title);
                    })
                    ->first();

                if ($planningTask) {
                    // Prefer specific location id when present, else fall back to task->location_id
                    $locationId = $planningTask->specificLocation?->id ?: $planningTask->task?->location_id;
                    if ($locationId) {
                        $item->update(['location_id' => $locationId]);
                        $this->line("Updated end_action item {$item->id} with location {$locationId}");
                        $updated++;
                    }
                }
            }
        }

        $this->info("Migration completed. Updated {$updated} items.");

        return 0;
    }
}
