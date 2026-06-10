<?php

namespace App\Services;

use App\Mail\PlanningReadyNotificationMail;
use App\Models\Planning;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlanningNotificationService
{
    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendPendingForPlanning(Planning $planning): array
    {
        $allUserCount = $planning->users()->count();
        $pendingUsers = $planning->users()
            ->whereNull('planning_user.notification_sent_at')
            ->get();

        $stats = [
            'sent' => 0,
            'skipped' => $allUserCount - $pendingUsers->count(),
            'failed' => 0,
        ];

        foreach ($pendingUsers as $user) {
            if ($this->sendToUser($planning, $user)) {
                $stats['sent']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * @return Collection<int, Planning>
     */
    public function planningsWithPendingNotificationsForDate(Carbon $date): Collection
    {
        return Planning::query()
            ->whereDate('planned_date', $date)
            ->whereHas('users', function ($query) {
                $query->whereNull('planning_user.notification_sent_at');
            })
            ->with(['users' => function ($query) {
                $query->whereNull('planning_user.notification_sent_at');
            }])
            ->get();
    }

    public function sendToUser(Planning $planning, User $user): bool
    {
        try {
            Mail::to($user->email)->send(new PlanningReadyNotificationMail($planning));

            $planning->users()->updateExistingPivot($user->id, [
                'notification_sent_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send planning notification to user '.$user->id.' for planning #'.$planning->id.': '.$e->getMessage());

            return false;
        }
    }
}
