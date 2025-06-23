<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Planning;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OfflinePlanningController extends Controller
{
    public function getFullPlanningData(Planning $planning): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        // Controleer toegang
        if (!$user->isAdmin() && !$planning->users->contains($user)) {
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
                    'task.benodigdheden',
                    'defaultTask.benodigdheden',
                    'specificLocation',
                    'completions' => function ($completionQuery) {
                        $completionQuery->with(['photos', 'user', 'reviewer'])->orderBy('created_at', 'desc');
                    },
                ]);
            },
            'endChecklistItems.benodigdheid',
            'locationTimers'
        ]);

        // Bereken benodigdheden voor de planning
        $benodigdheden = $this->calculateBenodigdheden($planning);

        // Genereer offline data
        $offlineData = [
            'last_sync' => now()->toISOString(),
            'sync_hash' => $this->generateSyncHash($planning),
            'expires_at' => now()->addHours(24)->toISOString(),
            'benodigdheden' => $benodigdheden,
            'user_permissions' => [
                'is_admin' => $user->isAdmin(),
                'can_complete_tasks' => true,
                'can_upload_photos' => true,
            ]
        ];
        
        return response()->json([
            'planning' => $planning,
            'offline_data' => $offlineData
        ]);
    }

    private function calculateBenodigdheden(Planning $planning): array
    {
        $benodigdheden = collect();

        // Verzamel benodigdheden van planning tasks
        foreach ($planning->planningTasks as $planningTask) {
            if ($planningTask->task && $planningTask->task->benodigdheden) {
                $benodigdheden = $benodigdheden->merge($planningTask->task->benodigdheden);
            }
            
            if ($planningTask->defaultTask && $planningTask->defaultTask->benodigdheden) {
                $benodigdheden = $benodigdheden->merge($planningTask->defaultTask->benodigdheden);
            }
        }

        // Unieke benodigdheden ophalen
        $uniqueBenodigdheden = $benodigdheden->unique('id')->values();

        return $uniqueBenodigdheden->map(function ($benodigdheid) {
            return [
                'id' => $benodigdheid->id,
                'naam' => $benodigdheid->naam,
                'beschrijving' => $benodigdheid->beschrijving,
                'type' => $benodigdheid->type ?? 'materiaal',
            ];
        })->toArray();
    }
    
    private function generateSyncHash(Planning $planning): string
    {
        $data = [
            'planning_updated_at' => $planning->updated_at->timestamp,
            'tasks_count' => $planning->planningTasks->count(),
            'completions_count' => $planning->planningTasks->sum(fn($pt) => $pt->completions->count()),
            'locations_count' => $planning->locations->count(),
        ];
        
        return hash('sha256', json_encode($data));
    }

    public function checkSyncStatus(Planning $planning): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        if (!$user->isAdmin() && !$planning->users->contains($user)) {
            abort(403, 'Geen toegang tot deze planning.');
        }

        $currentHash = $this->generateSyncHash($planning);
        
        return response()->json([
            'current_sync_hash' => $currentHash,
            'last_updated' => $planning->updated_at->toISOString(),
        ]);
    }
} 