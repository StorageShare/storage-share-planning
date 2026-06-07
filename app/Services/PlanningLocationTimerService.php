<?php

namespace App\Services;

use App\Models\Planning;
use App\Models\PlanningLocationTimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanningLocationTimerService
{
    public function parseHHMMToSeconds(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        $h = max(0, $h);
        $m = max(0, min(59, $m));

        return $h * 3600 + $m * 60;
    }

    public function formatSecondsHHMM(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * @return array{0:int|string|null,1:string}
     */
    public function resolveTimerTarget(int|string $locationId): array
    {
        if ($locationId === 'backlog') {
            return [null, 'backlog'];
        }
        if (is_string($locationId) && str_starts_with($locationId, 'travel_to_')) {
            return [str_replace('travel_to_', '', $locationId), 'travel'];
        }
        if ($locationId === 'travel_back') {
            return [null, 'travel_back'];
        }

        return [$locationId, 'location'];
    }

    public function findTimer(Planning $planning, int|string|null $actualLocationId, string $locationType): ?PlanningLocationTimer
    {
        return PlanningLocationTimer::where('planning_id', $planning->id)
            ->where('location_id', $actualLocationId)
            ->where('location_type', $locationType)
            ->first();
    }

    public function ensureTimerStarted(Planning $planning, int|string|null $actualLocationId, string $locationType): PlanningLocationTimer
    {
        return PlanningLocationTimer::create([
            'planning_id' => $planning->id,
            'location_id' => $actualLocationId,
            'location_type' => $locationType,
            'started_at' => now(),
            'ended_at' => null,
            'total_duration_seconds' => 0,
        ]);
    }

    public function buildTimerJson(PlanningLocationTimer $timer): JsonResponse
    {
        return response()->json([
            'started_at' => $timer->started_at?->toISOString(),
            'ended_at' => $timer->ended_at?->toISOString(),
            'total_duration' => $timer->total_duration_seconds,
        ]);
    }

    public function validateTimeInput(Request $request): string
    {
        $request->validate([
            'time' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
        ]);

        return (string) $request->string('time');
    }
}
