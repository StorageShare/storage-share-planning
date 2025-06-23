<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanningTask extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'planning_id',
        'task_id',
        'default_task_id',
        'location_id',
        'title',
        'description',
        'status',
        'review_notes',
        'completed_at',
        'completed_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'completed_at' => 'datetime',
        'status' => \App\Enums\TaskStatus::class,
    ];

    /**
     * Get the planning that owns the planning task.
     */
    public function planning(): BelongsTo
    {
        return $this->belongsTo(Planning::class);
    }

    /**
     * Get the specific task associated with this planning task (if any).
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the default task associated with this planning task (if any).
     */
    public function defaultTask(): BelongsTo
    {
        return $this->belongsTo(DefaultTask::class);
    }

    /**
     * Get the specific location for this planning task (especially for default task instances).
     */
    public function specificLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get the photos associated with the planning task execution.
     */
    public function planningTaskPhotos(): HasMany
    {
        return $this->hasMany(PlanningTaskPhoto::class);
    }

    /**
     * Get the completion history for the planning task.
     */
    public function completions(): HasMany
    {
        return $this->hasMany(PlanningTaskCompletion::class);
    }
}
