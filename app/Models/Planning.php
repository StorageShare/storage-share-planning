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
                // Distribute travel time only once, after completion
                $this->distributeTravelTimeToLocationsIfNeeded();
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
        // Only once
        if ($this->travel_time_distributed_at) {
            return;
        }

        // Need locations to distribute to
        $locations = $this->locations; // ordered by sort_order
        if (!$locations || $locations->count() === 0) {
            $this->travel_time_distributed_at = now();
            $this->travel_time_distributed_total_seconds = 0;
            $this->save();
            return;
        }

        // Sum all travel timers (inter-location and back to start)
        $travelSeconds = $this->locationTimers
            ->whereIn('location_type', ['travel', 'travel_back'])
            ->sum('total_duration_seconds');

        if ($travelSeconds <= 0) {
            $this->travel_time_distributed_at = now();
            $this->travel_time_distributed_total_seconds = 0;
            $this->save();
            return;
        }

        DB::transaction(function () use ($locations, $travelSeconds) {
            $locationCount = $locations->count();
            $baseShare = intdiv($travelSeconds, $locationCount);
            $remainder = $travelSeconds % $locationCount;
            $lastIndicesStart = $locationCount - $remainder;

            // Build a map of existing location timers
            $timersByLocationId = $this->locationTimers->where('location_type', 'location')->keyBy('location_id');

            foreach ($locations as $index => $location) {
                $extra = ($remainder > 0 && $index >= $lastIndicesStart) ? 1 : 0;
                $add = $baseShare + $extra;
                if ($add <= 0) continue;

                $timer = $timersByLocationId->get($location->id);
                if ($timer) {
                    $timer->increment('total_duration_seconds', $add);
                } else {
                    // Create a new location timer with the distributed travel seconds
                    PlanningLocationTimer::create([
                        'planning_id' => $this->id,
                        'location_id' => $location->id,
                        'location_type' => 'location',
                        'started_at' => null,
                        'ended_at' => null,
                        'total_duration_seconds' => $add,
                    ]);
                }
            }

            // Mark as done to avoid re-distribution
            $this->travel_time_distributed_at = now();
            $this->travel_time_distributed_total_seconds = $travelSeconds;
            $this->save();
        });
    }

    /**
     * Replace any previously distributed travel time on location timers
     * with a new even distribution based on current travel timers.
     */
    public function redistributeTravelTime(): void
    {
        $locations = $this->locations; // ordered by sort_order
        if (!$locations || $locations->count() === 0) {
            // Nothing to distribute
            $this->travel_time_distributed_at = now();
            $this->travel_time_distributed_total_seconds = 0;
            $this->save();
            return;
        }

        $newTravelSeconds = $this->locationTimers
            ->whereIn('location_type', ['travel', 'travel_back'])
            ->sum('total_duration_seconds');

        DB::transaction(function () use ($locations, $newTravelSeconds) {
            $locationCount = $locations->count();

            // Compute new shares
            $newBaseShare = $locationCount > 0 ? intdiv($newTravelSeconds, $locationCount) : 0;
            $newRemainder = $locationCount > 0 ? ($newTravelSeconds % $locationCount) : 0;
            $newLastIndicesStart = $locationCount - $newRemainder;

            // Compute previous shares from stored total
            $prevTotal = (int)($this->travel_time_distributed_total_seconds ?? 0);
            $prevBaseShare = $locationCount > 0 ? intdiv($prevTotal, $locationCount) : 0;
            $prevRemainder = $locationCount > 0 ? ($prevTotal % $locationCount) : 0;
            $prevLastIndicesStart = $locationCount - $prevRemainder;

            // Existing location timers map
            $timersByLocationId = $this->locationTimers->where('location_type', 'location')->keyBy('location_id');

            foreach ($locations as $index => $location) {
                $prevExtra = ($prevRemainder > 0 && $index >= $prevLastIndicesStart) ? 1 : 0;
                $prevShare = $prevBaseShare + $prevExtra;

                $newExtra = ($newRemainder > 0 && $index >= $newLastIndicesStart) ? 1 : 0;
                $newShare = $newBaseShare + $newExtra;

                $timer = $timersByLocationId->get($location->id);

                if ($timer) {
                    $base = max(0, (int)$timer->total_duration_seconds - $prevShare);
                    $timer->total_duration_seconds = $base + $newShare;
                    $timer->save();
                } else {
                    // Create with base 0 + newShare
                    PlanningLocationTimer::create([
                        'planning_id' => $this->id,
                        'location_id' => $location->id,
                        'location_type' => 'location',
                        'started_at' => null,
                        'ended_at' => null,
                        'total_duration_seconds' => $newShare,
                    ]);
                }
            }

            $this->travel_time_distributed_at = now();
            $this->travel_time_distributed_total_seconds = $newTravelSeconds;
            $this->save();
        });
    }
}
