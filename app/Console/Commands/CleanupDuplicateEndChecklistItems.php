<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EndChecklistItem;

class CleanupDuplicateEndChecklistItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checklist:cleanup-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup duplicate end checklist items (keep only items with photos or most recent)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up duplicate end checklist items...');

        $items = EndChecklistItem::with(['benodigdheid', 'planning'])->get();
        
        // Group items by type and identifier
        $groups = $items->groupBy(function ($item) {
            if ($item->type === 'material' && $item->benodigdheid_id) {
                return 'material_' . $item->benodigdheid_id . '_' . $item->planning_id;
            } else {
                return 'end_action_' . $item->title . '_' . $item->planning_id;
            }
        });

        $deleted = 0;

        foreach ($groups as $groupKey => $group) {
            if ($group->count() > 1) {
                $this->line("Found " . $group->count() . " items for group: {$groupKey}");
                
                // Sort by priority: 1) has photo, 2) most recent
                $sorted = $group->sortByDesc(function ($item) {
                    return [$item->photo_path ? 1 : 0, $item->created_at->timestamp];
                });
                
                $keepItem = $sorted->first();
                $deleteItems = $sorted->skip(1);
                
                $this->line("  Keeping item {$keepItem->id} (has photo: " . ($keepItem->photo_path ? 'yes' : 'no') . ")");
                
                foreach ($deleteItems as $deleteItem) {
                    $this->line("  Deleting item {$deleteItem->id} (has photo: " . ($deleteItem->photo_path ? 'yes' : 'no') . ")");
                    
                    // Only delete if it has no photo or if the kept item has a photo
                    if (!$deleteItem->photo_path || $keepItem->photo_path) {
                        $deleteItem->delete();
                        $deleted++;
                    } else {
                        $this->line("    Skipped - has photo and kept item has no photo");
                    }
                }
            }
        }

        $this->info("Cleanup completed. Deleted {$deleted} duplicate items.");
        return 0;
    }
}
