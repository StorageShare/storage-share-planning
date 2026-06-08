<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LocationReportController extends Controller
{
    /**
     * Display location visit and maintenance report.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Alleen administrators hebben toegang tot deze pagina.');
        }

        $sortBy = $request->input('sort_by', 'last_visit_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $searchTerm = $request->input('search_term', '');

        $validSortColumns = [
            'name',
            'last_visit_at',
            'last_controleronde_at',
            'last_schoonmaak_at',
            'visits_30d',
            'visits_365d',
        ];

        if (! in_array($sortBy, $validSortColumns, true)) {
            $sortBy = 'last_visit_at';
        }

        if (! in_array(strtolower($sortDirection), ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $locationsQuery = Location::query()
            ->select('locations.*')
            ->selectSub($this->lastCompletedAtSubquery(), 'last_visit_at')
            ->selectSub($this->lastCompletedAtSubquery('Controleronde'), 'last_controleronde_at')
            ->selectSub($this->lastCompletedAtSubquery('Schoonmaken'), 'last_schoonmaak_at')
            ->selectSub($this->visitCountSubquery(30), 'visits_30d')
            ->selectSub($this->visitCountSubquery(365), 'visits_365d');

        if ($searchTerm !== '') {
            $locationsQuery->whereRaw('LOWER(name) LIKE ?', [strtolower("%{$searchTerm}%")]);
        }

        if (in_array($sortBy, ['last_visit_at', 'last_controleronde_at', 'last_schoonmaak_at'], true)) {
            $locationsQuery->orderByRaw("{$sortBy} IS NULL")
                ->orderBy($sortBy, $sortDirection);
        } else {
            $locationsQuery->orderBy($sortBy, $sortDirection);
        }

        $perPage = $this->resolvePerPage($request, $locationsQuery);
        $locations = $locationsQuery->paginate($perPage)->appends($request->query());

        $locationIds = $locations->pluck('id');
        $trendData = $this->buildTrendData($locationIds);
        $averages = $this->calculateVisitAverages();

        return view($this->viewName('admin.locations.report'), compact(
            'locations',
            'sortBy',
            'sortDirection',
            'searchTerm',
            'trendData',
            'averages',
        ));
    }

    /**
     * Subquery for the latest completed_at for a location.
     *
     * @return \Closure(QueryBuilder): void
     */
    private function lastCompletedAtSubquery(?string $title = null): \Closure
    {
        return function (QueryBuilder $query) use ($title): void {
            $query->from('planning_tasks as pt')
                ->leftJoin('tasks as t', 't.id', '=', 'pt.task_id')
                ->whereNotNull('pt.completed_at')
                ->where(function (QueryBuilder $locationQuery): void {
                    $locationQuery->whereColumn('pt.location_id', 'locations.id')
                        ->orWhereColumn('t.location_id', 'locations.id');
                })
                ->selectRaw('MAX(pt.completed_at)');

            if ($title !== null) {
                $query->where(function (QueryBuilder $titleQuery) use ($title): void {
                    $titleQuery->where('pt.title', $title)
                        ->orWhere('t.title', $title);
                });
            }
        };
    }

    /**
     * Subquery for distinct planning visits within a day window.
     *
     * @return \Closure(QueryBuilder): void
     */
    private function visitCountSubquery(int $days): \Closure
    {
        return function (QueryBuilder $query) use ($days): void {
            $query->from('planning_tasks as pt')
                ->leftJoin('tasks as t', 't.id', '=', 'pt.task_id')
                ->whereNotNull('pt.completed_at')
                ->where('pt.completed_at', '>=', now()->subDays($days))
                ->where(function (QueryBuilder $locationQuery): void {
                    $locationQuery->whereColumn('pt.location_id', 'locations.id')
                        ->orWhereColumn('t.location_id', 'locations.id');
                })
                ->selectRaw('COUNT(DISTINCT pt.planning_id)');
        };
    }

    /**
     * Build monthly visit counts for the last 12 months per location.
     *
     * @param  Collection<int, int>  $locationIds
     * @return array<int, array<string, int>>
     */
    private function buildTrendData(Collection $locationIds): array
    {
        if ($locationIds->isEmpty()) {
            return [];
        }

        $months = collect();
        $start = now()->subMonths(11)->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $months->push($start->copy()->addMonths($i)->format('Y-m'));
        }

        $rows = DB::table('planning_tasks as pt')
            ->leftJoin('tasks as t', 't.id', '=', 'pt.task_id')
            ->whereNotNull('pt.completed_at')
            ->where('pt.completed_at', '>=', $start)
            ->where(function (QueryBuilder $query) use ($locationIds): void {
                $query->whereIn('pt.location_id', $locationIds)
                    ->orWhereIn('t.location_id', $locationIds);
            })
            ->select([
                DB::raw('COALESCE(pt.location_id, t.location_id) as resolved_location_id'),
                'pt.planning_id',
                'pt.completed_at',
            ])
            ->get();

        $trendData = [];
        foreach ($locationIds as $locationId) {
            $trendData[$locationId] = $months->mapWithKeys(fn (string $month): array => [$month => 0])->all();
        }

        $visitsByLocationMonth = [];
        foreach ($rows as $row) {
            $locationId = (int) $row->resolved_location_id;
            if (! $locationIds->contains($locationId)) {
                continue;
            }

            $month = Carbon::parse($row->completed_at)->format('Y-m');
            $visitKey = "{$locationId}:{$row->planning_id}:{$month}";

            if (isset($visitsByLocationMonth[$visitKey])) {
                continue;
            }

            $visitsByLocationMonth[$visitKey] = true;

            if (isset($trendData[$locationId][$month])) {
                $trendData[$locationId][$month]++;
            }
        }

        return $trendData;
    }

    /**
     * Calculate average visit counts across all locations.
     *
     * @return array{avg_visits_30d: float, avg_visits_365d: float}
     */
    private function calculateVisitAverages(): array
    {
        $stats = Location::query()
            ->selectSub($this->visitCountSubquery(30), 'visits_30d')
            ->selectSub($this->visitCountSubquery(365), 'visits_365d')
            ->get();

        return [
            'avg_visits_30d' => round((float) $stats->avg('visits_30d'), 2),
            'avg_visits_365d' => round((float) $stats->avg('visits_365d'), 2),
        ];
    }
}
