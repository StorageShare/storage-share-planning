<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Planning;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OfflinePlanningController extends Controller
{
    public function getFullPlanningData(Planning $planning): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Controleer toegang
        if (! $user->isAdmin() && ! $planning->users->contains($user)) {
            abort(403, 'Geen toegang tot deze planning.');
        }

        // Laad alle benodigde data voor offline gebruik
        $planning->load([
            'locations',
            'users',
            'planningTasks' => function ($query) {
                $query->with([
                    'task.location',
                    'task.taskPhotos',
                    'task.requirements',
                    'defaultTask.requirements',
                    'specificLocation',
                    'completions' => function ($completionQuery) {
                        $completionQuery->with(['photos', 'user', 'reviewer'])->orderBy('created_at', 'desc');
                    },
                ]);
            },
            'endChecklistItems.requirement',
            'locationTimers',
        ]);

        // Bereken requirements voor de planning
        $requirements = $this->calculateRequirements($planning);

        // Genereer offline data
        $offlineData = [
            'last_sync' => now()->toISOString(),
            'sync_hash' => $this->generateSyncHash($planning),
            'expires_at' => now()->addHours(24)->toISOString(),
            'requirements' => $requirements,
            'user_permissions' => [
                'is_admin' => $user->isAdmin(),
                'can_complete_tasks' => true,
                'can_upload_photos' => true,
            ],
        ];

        return response()->json([
            'planning' => $planning,
            'offline_data' => $offlineData,
        ]);
    }

    /**
     * @return array<int, array{id: int, naam: string, beschrijving: string, type: string}>
     */
    private function calculateRequirements(Planning $planning): array
    {
        $requirements = collect();

        // Verzamel requirements van planning tasks
        foreach ($planning->planningTasks as $planningTask) {
            if ($planningTask->task && $planningTask->task->requirements->count() > 0) {
                $requirements = $requirements->merge($planningTask->task->requirements);
            }

            if ($planningTask->defaultTask && $planningTask->defaultTask->requirements->count() > 0) {
                $requirements = $requirements->merge($planningTask->defaultTask->requirements);
            }
        }

        // Unieke requirements ophalen
        $uniqueRequirements = $requirements->unique('id')->values();

        return $uniqueRequirements->map(function ($requirement) {
            return [
                'id' => $requirement->id,
                'naam' => $requirement->name,
                'beschrijving' => $requirement->description,
                'type' => $requirement->type ?? 'materiaal',
            ];
        })->toArray();
    }

    private function generateSyncHash(Planning $planning): string
    {
        $data = [
            'planning_updated_at' => $planning->updated_at->timestamp,
            'tasks_count' => $planning->planningTasks->count(),
            'completions_count' => $planning->planningTasks->sum(fn ($pt) => $pt->completions->count()),
            'locations_count' => $planning->locations->count(),
        ];

        return hash('sha256', json_encode($data));
    }

    public function checkSyncStatus(Planning $planning): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isAdmin() && ! $planning->users->contains($user)) {
            abort(403, 'Geen toegang tot deze planning.');
        }

        $currentHash = $this->generateSyncHash($planning);

        return response()->json([
            'current_sync_hash' => $currentHash,
            'last_updated' => $planning->updated_at->toISOString(),
        ]);
    }
}
