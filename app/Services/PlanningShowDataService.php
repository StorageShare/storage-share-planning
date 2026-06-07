<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Planning;
use Illuminate\Support\Collection;

class PlanningShowDataService
{
    public function __construct(
        private TravelTimeService $travelTimeService
    ) {}

    /**
     * @return array{
     *   travelTimes: array<string, mixed>|null,
     *   timeOverview: array{task_minutes: int, travel_minutes: int|float, total_minutes: int|float},
     *   onLocationTimers: Collection<int, mixed>,
     *   travelToTimers: Collection<int, mixed>,
     *   travelBackTimer: mixed,
     *   actualTotals: array{travel_seconds: int, on_location_seconds: int},
     *   allLocations: Collection<int, Location>
     * }
     */
    public function build(Planning $planning): array
    {
        $travelTimes = null;
        if ($planning->locations->count() > 1) {
            $travelTimes = $this->travelTimeService->calculateTravelTimesForSequence(
                $planning->locations->all(),
                $planning->start_address
            );
        }

        $totalTaskMinutes = $planning->planningTasks->sum(function ($planningTask) {
            if ($planningTask->task && isset($planningTask->task->estimated_time_minutes)) {
                return (int) $planningTask->task->estimated_time_minutes;
            }
            if ($planningTask->defaultTask && isset($planningTask->defaultTask->estimated_time_minutes)) {
                return (int) $planningTask->defaultTask->estimated_time_minutes;
            }

            return 0;
        });

        $onLocationTimers = $planning->locationTimers->where('location_type', 'location')->keyBy('location_id');
        $travelToTimers = $planning->locationTimers->where('location_type', 'travel')->keyBy('location_id');
        $travelBackTimer = $planning->locationTimers->firstWhere('location_type', 'travel_back');

        $timeOverview = [
            'task_minutes' => $totalTaskMinutes,
            'travel_minutes' => $travelTimes ? $travelTimes['total_duration_minutes'] : 0,
            'total_minutes' => $totalTaskMinutes + ($travelTimes ? $travelTimes['total_duration_minutes'] : 0),
        ];

        $actualTravelSeconds = $planning->locationTimers
            ->whereIn('location_type', ['travel', 'travel_back'])
            ->sum('total_duration_seconds');
        $actualOnLocationSeconds = $planning->locationTimers
            ->where('location_type', 'location')
            ->sum('total_duration_seconds');

        $actualTotals = [
            'travel_seconds' => (int) $actualTravelSeconds,
            'on_location_seconds' => (int) $actualOnLocationSeconds,
        ];

        $allLocations = Location::orderBy('name')->get(['id', 'name']);

        return compact(
            'travelTimes',
            'timeOverview',
            'onLocationTimers',
            'travelToTimers',
            'travelBackTimer',
            'actualTotals',
            'allLocations'
        );
    }
}
