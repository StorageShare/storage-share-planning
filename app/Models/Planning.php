<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// Import PlanningTask
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Planning extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'planned_date',
        'notes',
        'status',
        'created_by',
        'start_address',
        'start_time',
        'travel_time_distributed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'planned_date' => 'datetime',
        'travel_time_distributed_at' => 'datetime',
        'travel_time_distributed_total_seconds' => 'integer',
    ];

    /**
     * De locaties die bij deze planning horen.
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_planning')
            ->withPivot('sort_order')
            ->orderBy('sort_order');
    }

    /**
     * Get the tasks for the planning.
     */
    public function planningTasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }

    /**
     * Get the end checklist items for the planning.
     */
    public function endChecklistItems(): HasMany
    {
        return $this->hasMany(EndChecklistItem::class);
    }

    /**
     * The users that belong to the planning.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get the user who created the planning.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the location timers for the planning.
     */
    public function locationTimers(): HasMany
    {
        return $this->hasMany(PlanningLocationTimer::class);
    }

    /**
     * Check if all end checklist items are approved.
     */
    public function hasApprovedEndChecklist(): bool
    {
        $endChecklistItems = $this->endChecklistItems;

        if ($endChecklistItems->isEmpty()) {
            return false; // No end checklist submitted yet
        }

        return $endChecklistItems->every(function ($item) {
            return $item->isApproved();
        });
    }

    /**
     * Check if end checklist has been submitted (all items have photos).
     */
    public function hasSubmittedEndChecklist(): bool
    {
        $endChecklistItems = $this->endChecklistItems;

        if ($endChecklistItems->isEmpty()) {
            return false;
        }

        return $endChecklistItems->every(function ($item) {
            return !empty($item->photo_path);
        });
    }

    /**
     * Check if all tasks in the planning are completed and update the planning status accordingly.
     */
    public function checkAndUpdateStatus(): void
    {
        // Eager load the planningTasks relationship to prevent N+1 issues
        $this->loadMissing('planningTasks', 'endChecklistItems', 'locations', 'locationTimers');

        if ($this->planningTasks->isEmpty()) {
            if ($this->status !== 'completed') {
                $this->status = 'open';
                $this->save();
            }

            return;
        }

        // Check if every single task is marked as 'completed'
        $allTasksCompleted = $this->planningTasks->every(function ($task) {
            $status = $task->status;
            // Accept both enum and string values for status
            if ($status instanceof \App\Enums\TaskStatus) {
                return $status === \App\Enums\TaskStatus::COMPLETED;
            }
            return $status === (\App\Enums\TaskStatus::COMPLETED->value ?? 'completed') || $status === 'completed';
        });

        if ($allTasksCompleted) {
            // Tasks are completed, check if end checklist is also approved
            if ($this->hasApprovedEndChecklist()) {
                if ($this->status !== 'completed') {
                    $this->status = 'completed';
                    $this->save();
                }
                // Travel time distribution among locations removed; no action needed here
            } else {
                // Tasks completed but end checklist not approved yet
                if ($this->status !== 'pending_end_checklist') {
                    $this->status = 'pending_end_checklist';
                    $this->save();
                }
            }
        } else {
            // If not all tasks are completed, but the planning was, revert status.
            if (in_array($this->status, ['completed', 'pending_end_checklist'])) {
                $this->status = 'in_progress'; // Or 'open', depending on your states
                $this->save();
            }
        }
    }
    /**
     * Evenly distribute total travel time among all locations once, after completion.
     */
    public function distributeTravelTimeToLocationsIfNeeded(): void
    {
        // Travel time splitting among locations has been removed. This method is now a no-op.
        return;
    }

    /**
     * Replace any previously distributed travel time on location timers
     * with a new even distribution based on current travel timers.
     */
    public function redistributeTravelTime(): void
    {
        // Travel time splitting among locations has been removed. This method is now a no-op.
        return;
    }
}
