<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\PlanningTaskFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Planning $planning
 * @property-read Task|null $task
 * @property-read DefaultTask|null $defaultTask
 * @property-read Location|null $specificLocation
 * @property-read Collection<int, PlanningTaskCompletion> $completions
 * @property string|null $feedback_emails
 */
class PlanningTask extends Model
{
    /**
     * @use HasFactory<PlanningTaskFactory>
     */
    use HasFactory;

    protected $fillable = [
        'planning_id',
        'task_id',
        'default_task_id',
        'vehicle_task_id',
        'location_id',
        'title',
        'description',
        'feedback_information',
        'feedback_owner_name',
        'feedback_emails',
        'status',
        'review_notes',
        'completed_at',
        'completed_notes',
        'estimated_time_minutes',
        'is_vehicle_task',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'completed_at' => 'datetime',
        'status' => TaskStatus::class,
        'is_vehicle_task' => 'boolean',
    ];

    /**
     * Get the planning that owns the planning task.
     *
     * @return BelongsTo<Planning, $this>
     */
    public function planning(): BelongsTo
    {
        return $this->belongsTo(Planning::class);
    }

    /**
     * Get the specific task associated with this planning task (if any).
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the default task associated with this planning task (if any).
     *
     * @return BelongsTo<DefaultTask, $this>
     */
    public function defaultTask(): BelongsTo
    {
        return $this->belongsTo(DefaultTask::class);
    }

    /**
     * Get the specific location for this planning task (especially for default task instances).
     *
     * @return BelongsTo<Location, $this>
     */
    public function specificLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Vehicle task linkage when this planning task represents a vehicle task.
     *
     * @return BelongsTo<VehicleTask, $this>
     */
    public function vehicleTask(): BelongsTo
    {
        return $this->belongsTo(VehicleTask::class);
    }

    /**
     * Get the photos associated with the planning task execution.
     *
     * @return HasMany<PlanningTaskPhoto, $this>
     */
    public function planningTaskPhotos(): HasMany
    {
        return $this->hasMany(PlanningTaskPhoto::class);
    }

    /**
     * Get the completion history for the planning task.
     *
     * @return HasMany<PlanningTaskCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(PlanningTaskCompletion::class);
    }
}
