<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningLocationTimer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BvStatsController extends Controller
{
    /**
     * Show BV statistics with time filtering
     */
    public function index(Request $request): View
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Alleen administrators hebben toegang tot deze pagina.');
        }

        // Default to current month
        $currentMonth = Carbon::now();
        $defaultFromDate = $currentMonth->copy()->startOfMonth()->format('Y-m-d');
        $defaultToDate = $currentMonth->copy()->endOfMonth()->format('Y-m-d');

        $fromDate = $request->get('from_date', $defaultFromDate);
        $toDate = $request->get('to_date', $defaultToDate);

        // Validate dates
        $fromCarbon = Carbon::parse($fromDate);
        $toCarbon = Carbon::parse($toDate);

        // Get all unique BVs
        $bvs = Location::whereNotNull('bv')
            ->where('bv', '!=', '')
            ->distinct()
            ->pluck('bv')
            ->sort()
            ->values();

        // Get plannings within date range
        $plannings = Planning::whereBetween('planned_date', [$fromCarbon, $toCarbon])
            ->with([
                'locations',
                'locationTimers.location',
                'users', // Include users to count how many people worked
            ])
            ->get();

        $bvStats = [];

        foreach ($bvs as $bv) {
            $bvStats[$bv] = [
                'bv' => $bv,
                'total_work_seconds' => 0,
                'total_travel_seconds' => 0,
                'location_count' => 0,
                'planning_count' => 0,
                'locations' => [],
            ];
        }

        foreach ($plannings as $planning) {
            // Travel time distribution among locations removed; no safety-net redistribution.

            $planningLocations = $planning->locations;
            $planningTimers = $planning->locationTimers->keyBy(function ($timer) {
                return $timer->location_id ?? 'travel_' . $timer->location_type;
            });

            if ($planningLocations->isEmpty()) {
                continue;
            }

            // Get unique BVs in this planning
            $planningBvs = $planningLocations->whereNotNull('bv')
                ->where('bv', '!=', '')
                ->pluck('bv')
                ->unique();

            if ($planningBvs->isEmpty()) {
                continue;
            }

            // Get number of users (workers) for this planning
            $userCount = $planning->users->count();
            $userMultiplier = $userCount > 0 ? $userCount : 1; // Fallback to 1 if no users assigned

            foreach ($planningBvs as $bv) {
                $bvStats[$bv]['planning_count']++;
            }

            // Calculate work time per location, multiplied by number of users
            foreach ($planningLocations as $location) {
                if (empty($location->bv)) {
                    continue;
                }

                $timer = $planningTimers->get($location->id);
                $workSeconds = $timer ? $timer->total_duration_seconds : 0;

                // Multiply by number of users to get actual worked hours
                $actualWorkSeconds = $workSeconds * $userMultiplier;

                $bvStats[$location->bv]['total_work_seconds'] += $actualWorkSeconds;
                $bvStats[$location->bv]['location_count']++;

                if (!isset($bvStats[$location->bv]['locations'][$location->id])) {
                    $bvStats[$location->bv]['locations'][$location->id] = [
                        'name' => $location->name,
                        'total_seconds' => 0,
                        'visit_count' => 0,
                    ];
                }

                $bvStats[$location->bv]['locations'][$location->id]['total_seconds'] += $actualWorkSeconds;
                $bvStats[$location->bv]['locations'][$location->id]['visit_count']++;
            }

            // If travel has already been distributed into location timers, skip counting travel timers to avoid double counting
            if (isset($planning->travel_time_distributed_at) && $planning->travel_time_distributed_at) {
                // Do nothing: location timers already include travel time
            } else {
                // Distribute travel time between locations based on BV, multiplied by users
                $travelTimers = $planningTimers->filter(function ($timer) {
                    return in_array($timer->location_type, ['travel', 'travel_back']);
                });

                foreach ($travelTimers as $travelTimer) {
                    $travelSeconds = $travelTimer->total_duration_seconds;

                    if ($travelSeconds > 0 && $planningBvs->count() > 0) {
                        // Multiply by users and distribute equally among BVs in this planning
                        $actualTravelSeconds = $travelSeconds * $userMultiplier;
                        $travelPerBv = $actualTravelSeconds / $planningBvs->count();

                        foreach ($planningBvs as $bv) {
                            $bvStats[$bv]['total_travel_seconds'] += $travelPerBv;
                        }
                    }
                }

                // Distribute start/end travel time among all locations in planning, multiplied by users (legacy backlog type)
                $backlogTimer = $planningTimers->get('travel_backlog') ?? $planningTimers->where('location_type', 'backlog')->first();
                if ($backlogTimer && $backlogTimer->total_duration_seconds > 0) {
                    $startEndTravelSeconds = $backlogTimer->total_duration_seconds;

                    if ($planningBvs->count() > 0) {
                        // Multiply by users and distribute
                        $actualStartEndTravelSeconds = $startEndTravelSeconds * $userMultiplier;
                        $startEndTravelPerBv = $actualStartEndTravelSeconds / $planningBvs->count();

                        foreach ($planningBvs as $bv) {
                            $bvStats[$bv]['total_travel_seconds'] += $startEndTravelPerBv;
                        }
                    }
                }
            }
        }

        // Sort BV stats by total time (work + travel) descending
        uasort($bvStats, function ($a, $b) {
            $totalA = $a['total_work_seconds'] + $a['total_travel_seconds'];
            $totalB = $b['total_work_seconds'] + $b['total_travel_seconds'];
            return $totalB <=> $totalA;
        });

        return view('admin.bv-stats.index', compact(
            'bvStats',
            'fromDate',
            'toDate',
            'fromCarbon',
            'toCarbon'
        ));
    }
}
