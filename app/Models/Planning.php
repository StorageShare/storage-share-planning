<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\PlanningFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// Import PlanningTask
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property-read string $title
 */
class Planning extends Model
{
    /**
     * @use HasFactory<PlanningFactory>
     */
    use HasFactory;

    protected $fillable = [
        'planned_date',
        'notes',
        'status',
        'created_by',
        'start_address',
        'start_time',
        'travel_time_distributed_at',
        'vehicle_id',
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
     *
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_planning')
            ->withPivot('sort_order')
            // Ensure we always order by the pivot column, not a base table column
            ->orderByPivot('sort_order');
    }

    /**
     * Get the tasks for the planning.
     *
     * @return HasMany<PlanningTask, $this>
     */
    public function planningTasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }

    /**
     * Get the end checklist items for the planning.
     *
     * @return HasMany<EndChecklistItem, $this>
     */
    public function endChecklistItems(): HasMany
    {
        return $this->hasMany(EndChecklistItem::class);
    }

    /**
     * The users that belong to the planning.
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get the user who created the planning.
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Assigned vehicle for this planning.
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the location timers for the planning.
     *
     * @return HasMany<PlanningLocationTimer, $this>
     */
    public function locationTimers(): HasMany
    {
        return $this->hasMany(PlanningLocationTimer::class);
    }

    /**
     * @return HasMany<PlanningComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PlanningComment::class);
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
            return $item->photos()->exists() || !empty($item->photo_path);
        });
    }

    /**
     * Check if all tasks in the planning are completed and update the planning status accordingly.
     */
    public function checkAndUpdateStatus(): void
    {
        // Force reload the relationships to ensure we have the latest status from the database
        $this->load(['planningTasks', 'endChecklistItems', 'locations', 'locationTimers']);

        if ($this->planningTasks->isEmpty()) {
            if ($this->status !== 'completed') {
                $this->status = 'open';
                $this->save();
            }

            return;
        }

        // Check if every single task is marked as 'completed'
        $allTasksCompleted = $this->planningTasks->every(function ($task) {
            return $task->status === TaskStatus::COMPLETED;
        });

        if ($allTasksCompleted) {
            // Tasks are completed, check if end checklist is also approved
            if ($this->hasApprovedEndChecklist()) {
                if ($this->status !== 'completed') {
                    DB::transaction(function () {
                        // Cleanup uncompleted default tasks when automatically completing
                        $this->cleanupUncompletedDefaultTasks();

                        $this->status = 'completed';
                        $this->save();
                    });
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
     * Cleanup uncompleted default tasks when the planning is marked as completed.
     * This also removes "floating" default tasks from the backlog for the locations in this planning.
     */
    public function cleanupUncompletedDefaultTasks(): void
    {
        // 1. Cleanup tasks directly linked to this planning
        $uncompletedDefaultPlanningTasks = $this->planningTasks()
            ->whereNotNull('default_task_id')
            ->where('status', '!=', TaskStatus::COMPLETED->value)
            ->get();

        foreach ($uncompletedDefaultPlanningTasks as $planningTask) {
            if ($planningTask->task_id) {
                Task::where('id', $planningTask->task_id)->delete();
            }
            $planningTask->delete();
        }

        // 2. Cleanup "floating" default tasks in the backlog for the locations in this planning.
        // We identify these by matching titles of active DefaultTasks for the locations of this planning.
        $locationIds = $this->locations()->pluck('locations.id');

        if ($locationIds->isNotEmpty()) {
            // Get all default task titles that could apply to these locations
            $defaultTaskTitles = DefaultTask::where(function ($query) use ($locationIds) {
                $query->where('applies_to_all_locations', true)
                    ->orWhereHas('locations', function ($q) use ($locationIds) {
                        $q->whereIn('locations.id', $locationIds);
                    });
            })->pluck('title')->unique();

            if ($defaultTaskTitles->isNotEmpty()) {
                Task::whereIn('location_id', $locationIds)
                    ->where('status', TaskStatus::OPEN->value)
                    ->whereIn('title', $defaultTaskTitles)
                    ->whereDoesntHave('planningTasks') // Only tasks NOT currently in any planning (backlog)
                    ->delete();
            }
        }
    }
}
