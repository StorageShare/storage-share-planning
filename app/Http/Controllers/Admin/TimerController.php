<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Planning;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TimerController extends Controller
{
    /**
     * Display timer overview for all plannings
     */
    public function index(Request $request): View
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Alleen administrators hebben toegang tot deze pagina.');
        }

        $query = Planning::with([
            'locationTimers.location',
            'locations',
            'users'
        ]);

        // Filter by date if provided
        if ($request->filled('date_from')) {
            $query->where('planned_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('planned_date', '<=', $request->date_to);
        }

        // Filter by user if provided
        if ($request->filled('user_id')) {
            $query->whereHas('users', function ($q) use ($request) {
                $q->where('users.id', $request->user_id);
            });
        }

        $perPage = $this->resolvePerPage($request, $query, 20);
        $plannings = $query->orderBy('planned_date', 'desc')->paginate($perPage)->withQueryString();

        // Get all users for filter dropdown
        $users = User::orderBy('name')->get();

        return view('admin.timers.index', compact('plannings', 'users'));
    }

    /**
     * Show detailed timer information for a specific planning
     */
    public function show(Planning $planning): View
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Alleen administrators hebben toegang tot deze pagina.');
        }

        $planning->load([
            'locationTimers.location',
            'locations',
            'users',
            'planningTasks.task.location',
            'planningTasks.defaultTask.locations'
        ]);

        // Calculate timer statistics
        $totalDurationSeconds = $planning->locationTimers->sum('total_duration_seconds');

        $timersByLocation = $planning->locationTimers->map(function ($timer) {
            $hours = floor(($timer->total_duration_seconds ?? 0) / 3600);
            $minutes = floor((($timer->total_duration_seconds ?? 0) % 3600) / 60);
            $seconds = ($timer->total_duration_seconds ?? 0) % 60;

            return [
                'timer' => $timer,
                'location_name' => $timer->location ? $timer->location->name :
                    ($timer->location_type === 'backlog' ? 'Backlog' : ($timer->location_type === 'travel_back' ? 'Terugreistijd' : 'Reistijd')),
                'formatted_duration' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
                'is_active' => $timer->started_at && !$timer->ended_at,
            ];
        });

        // Build distributed totals per unique location (on-location time + equal share of travel times)
        $onLocationByLocation = $planning->locationTimers
            ->where('location_type', 'location')
            ->groupBy('location_id')
            ->map(function ($group) {
                $seconds = $group->sum(function ($t) { return $t->total_duration_seconds ?? 0; });
                /** @var \App\Models\PlanningLocationTimer|null $first */
                $first = $group->first();
                $locationName = $first && $first->location ? $first->location->name : 'Onbekende locatie';
                return [
                    'location_id' => $first?->location_id,
                    'location_name' => $locationName,
                    'base_seconds' => $seconds,
                ];
            })
            ->values();

        $locationCount = $onLocationByLocation->count();
        $travelSeconds = $planning->locationTimers->where('location_type', 'travel')->sum(function ($t) { return $t->total_duration_seconds ?? 0; });
        $travelBackSeconds = $planning->locationTimers->where('location_type', 'travel_back')->sum(function ($t) { return $t->total_duration_seconds ?? 0; });
        $totalTravelToDistribute = $travelSeconds + $travelBackSeconds;

        $distributedByLocation = collect();
        if ($locationCount > 0) {
            $share = intdiv($totalTravelToDistribute, $locationCount);
            $remainder = $totalTravelToDistribute % $locationCount;

            foreach ($onLocationByLocation as $idx => $loc) {
                $extra = $idx < $remainder ? 1 : 0; // distribute remainder seconds
                $adjustedSeconds = $loc['base_seconds'] + $share + $extra;
                $hours = floor($adjustedSeconds / 3600);
                $minutes = floor(($adjustedSeconds % 3600) / 60);
                $seconds = $adjustedSeconds % 60;
                $distributedByLocation->push(array_merge($loc, [
                    'distributed_extra_seconds' => $share + $extra,
                    'adjusted_seconds' => $adjustedSeconds,
                    'formatted_adjusted' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
                ]));
            }
        }

        return view('admin.timers.show', compact('planning', 'timersByLocation', 'totalDurationSeconds', 'distributedByLocation', 'totalTravelToDistribute'));
    }

    /**
     * Get live timer data for a planning (AJAX endpoint)
     */
    public function getLiveData(Planning $planning)
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Alleen administrators hebben toegang tot deze pagina.');
        }

        $timers = $planning->locationTimers()->with('location')->get();

        $liveData = $timers->map(function ($timer) {
            $currentSeconds = 0;
            if ($timer->started_at && !$timer->ended_at) {
                // Timer is running - calculate current time
                $currentSeconds = ($timer->total_duration_seconds ?? 0) + $timer->started_at->diffInSeconds(now());
            } else {
                // Timer is stopped
                $currentSeconds = $timer->total_duration_seconds ?? 0;
            }

            $hours = floor($currentSeconds / 3600);
            $minutes = floor(($currentSeconds % 3600) / 60);
            $seconds = $currentSeconds % 60;

            return [
                'id' => $timer->id,
                'location_id' => $timer->location_id,
                'location_name' => $timer->location ? $timer->location->name :
                    ($timer->location_type === 'backlog' ? 'Backlog' : 'Reistijd'),
                'location_type' => $timer->location_type,
                'formatted_duration' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
                'total_seconds' => $currentSeconds,
                'is_active' => $timer->started_at && !$timer->ended_at,
                'started_at' => $timer->started_at?->toISOString(),
                'ended_at' => $timer->ended_at?->toISOString(),
            ];
        });

        return response()->json($liveData);
    }

    /**
     * Export timer data as CSV
     */
    public function export(Request $request)
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Alleen administrators hebben toegang tot deze pagina.');
        }

        $query = Planning::with([
            'locationTimers.location',
            'locations',
            'users'
        ]);

        // Apply same filters as index
        if ($request->filled('date_from')) {
            $query->where('planned_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('planned_date', '<=', $request->date_to);
        }

        if ($request->filled('user_id')) {
            $query->whereHas('users', function ($q) use ($request) {
                $q->where('users.id', $request->user_id);
            });
        }

        $plannings = $query->orderBy('planned_date', 'desc')->get();

        $filename = 'timer_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($plannings) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Planning ID',
                'Geplande Datum',
                'Gebruikers',
                'Locatie',
                'Locatie Type',
                'Gewerkte Tijd',
                'Reistijddeel (gelijk verdeeld)',
                'Totaal incl. reistijd',
                'Gestart Om',
                'Gestopt Om',
                'Status'
            ]);

            foreach ($plannings as $planning) {
                $userNames = $planning->users->pluck('name')->join(', ');

                // Voor deze planning: bereken gelijk verdeelde reistijd per locatie
                $onLocationByLocation = $planning->locationTimers
                    ->where('location_type', 'location')
                    ->groupBy('location_id')
                    ->map(function ($group) {
                        $seconds = $group->sum(function ($t) { return $t->total_duration_seconds ?? 0; });
                        /** @var \App\Models\PlanningLocationTimer|null $first */
                        $first = $group->first();
                        return [
                            'location_id' => $first?->location_id,
                            'base_seconds' => $seconds,
                        ];
                    })
                    ->values();

                $locationCount = $onLocationByLocation->count();
                $travelSeconds = $planning->locationTimers->where('location_type', 'travel')->sum(function ($t) { return $t->total_duration_seconds ?? 0; });
                $travelBackSeconds = $planning->locationTimers->where('location_type', 'travel_back')->sum(function ($t) { return $t->total_duration_seconds ?? 0; });
                $totalTravelToDistribute = $travelSeconds + $travelBackSeconds;

                // Bouw een map met reistijd-deel per locatie_id
                $shareByLocationId = [];
                if ($locationCount > 0) {
                    $share = intdiv($totalTravelToDistribute, $locationCount);
                    $remainder = $totalTravelToDistribute % $locationCount;
                    foreach ($onLocationByLocation as $idx => $loc) {
                        $extra = $idx < $remainder ? 1 : 0; // verdeel restseconden
                        $shareByLocationId[$loc['location_id']] = $share + $extra;
                    }
                }

                foreach ($planning->locationTimers as $timer) {
                    $baseSeconds = (int) ($timer->total_duration_seconds ?? 0);
                    $hours = floor($baseSeconds / 3600);
                    $minutes = floor(($baseSeconds % 3600) / 60);
                    $seconds = $baseSeconds % 60;
                    $formattedDuration = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

                    // Bepaal locatie naam, inclusief onderscheid voor terugreis
                    $locationName = $timer->location ? $timer->location->name : (
                        $timer->location_type === 'backlog' ? 'Backlog' : (
                            $timer->location_type === 'travel_back' ? 'Terugreistijd' : 'Reistijd'
                        )
                    );

                    $status = $timer->started_at && !$timer->ended_at ? 'Actief' : 'Gestopt';

                    // Gelijk verdeelde reistijd alleen toekennen aan locatie-entries
                    $distributedShareSeconds = 0;
                    if ($timer->location_type === 'location' && $timer->location_id !== null) {
                        $distributedShareSeconds = $shareByLocationId[$timer->location_id] ?? 0;
                    }

                    $adjustedSeconds = $baseSeconds + $distributedShareSeconds;
                    $shareH = floor($distributedShareSeconds / 3600);
                    $shareM = floor(($distributedShareSeconds % 3600) / 60);
                    $shareS = $distributedShareSeconds % 60;
                    $formattedShare = sprintf('%02d:%02d:%02d', $shareH, $shareM, $shareS);

                    $adjH = floor($adjustedSeconds / 3600);
                    $adjM = floor(($adjustedSeconds % 3600) / 60);
                    $adjS = $adjustedSeconds % 60;
                    $formattedAdjusted = sprintf('%02d:%02d:%02d', $adjH, $adjM, $adjS);

                    fputcsv($file, [
                        $planning->id,
                        $planning->planned_date->format('d-m-Y'),
                        $userNames,
                        $locationName,
                        ucfirst($timer->location_type),
                        $formattedDuration,
                        $formattedShare,
                        $formattedAdjusted,
                        $timer->created_at?->format('d-m-Y H:i:s'),
                        $timer->ended_at?->format('d-m-Y H:i:s'),
                        $status
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
