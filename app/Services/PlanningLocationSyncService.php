<?php

namespace App\Services;

use App\Models\Planning;

class PlanningLocationSyncService
{
    /**
     * @param  array<int,int>  $locationIds
     * @param  array<int|string, mixed>|null  $checkInactiveSpaces
     */
    public function sync(Planning $planning, array $locationIds, ?string $locationOrder, ?array $checkInactiveSpaces = []): void
    {
        $orderedIds = $locationOrder ? array_values(array_filter(
            array_map(static fn ($v) => (int) trim((string) $v), explode(',', $locationOrder)),
            static fn ($v) => $v > 0
        )) : [];

        $selectedSet = array_flip(array_map('intval', $locationIds));
        $finalOrderedIds = [];
        foreach ($orderedIds as $id) {
            if (isset($selectedSet[$id]) && ! in_array($id, $finalOrderedIds, true)) {
                $finalOrderedIds[] = $id;
            }
        }
        foreach ($locationIds as $id) {
            $iid = (int) $id;
            if (! in_array($iid, $finalOrderedIds, true)) {
                $finalOrderedIds[] = $iid;
            }
        }

        $locationsToSync = [];
        foreach ($finalOrderedIds as $index => $locationId) {
            $locationsToSync[(int) $locationId] = [
                'sort_order' => $index,
                'check_inactive_spaces' => (bool) ($checkInactiveSpaces[$locationId] ?? false),
            ];
        }

        $planning->locations()->sync($locationsToSync);
    }
}
