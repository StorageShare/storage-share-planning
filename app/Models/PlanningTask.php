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
        'room_identifier',
        'room_group',
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
     * @var list<string>
     */
    protected $appends = [
        'photos',
        'skip_photos',
    ];

    /**
     * Get the photos associated with the task completion.
     *
     * @return list<array{id: int, url: string, room: string|null, location_id: int|null}>
     */
    public function getPhotosAttribute(): array
    {
        $latestCompletion = $this->completions()
            ->where(function ($query) {
                $query->where('review_outcome', '!=', 'reopened')
                    ->orWhereNull('review_outcome');
            })
            ->latest()
            ->first();

        if ($latestCompletion) {
            return $this->mapPhotos($latestCompletion->photos);
        }

        return [];
    }

    /**
     * Get the skip photos associated with the task.
     *
     * @return list<array{id: int, url: string, room: string|null, location_id: int|null}>
     */
    public function getSkipPhotosAttribute(): array
    {
        $latestSkip = $this->completions()
            ->where('review_outcome', 'skipped')
            ->latest()
            ->first();

        if ($latestSkip) {
            return $this->mapPhotos($latestSkip->photos);
        }

        return [];
    }

    /**
     * Map completion photos into a serializable array.
     *
     * @param  Collection<int, PlanningTaskCompletionPhoto>  $photos
     * @return list<array{id: int, url: string, room: string|null, location_id: int|null}>
     */
    protected function mapPhotos(Collection $photos): array
    {
        return $photos->map(fn (PlanningTaskCompletionPhoto $p): array => [
            'id' => $p->id,
            'url' => $p->url,
            'room' => $p->room,
            'location_id' => null,
        ])->values()->all();
    }

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
     * Get the location for this planning task.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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
