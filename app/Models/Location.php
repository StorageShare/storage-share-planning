<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Models\ExternalTask;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string|null $name
 * @property string|null $address
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $bv
 */
class Location extends Model
{
    /**
     * @use HasFactory<\Database\Factories\LocationFactory>
     */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'external_id',
        'sync_external_id',
        'name',
        'last_synced_at',
        'deleted_at',
        'latitude',
        'longitude',
        'lift',
        'bv',
        'type_deur',
        'total_m2_net',
        'total_m2_gross',
        'total_rooms',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'deleted_at' => 'datetime',
        'total_m2_net' => 'decimal:2',
        'total_m2_gross' => 'decimal:2',
        'total_rooms' => 'integer',
        'lift' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Check if the location was manually created.
     *
     * @return bool
     */
    public function isManuallyCreated(): bool
    {
        return empty($this->external_id);
    }

    /**
     * Get the tasks for the location.
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the external tasks for the location.
     * @return HasMany<ExternalTask, $this>
     */
    public function externalTasks(): HasMany
    {
        return $this->hasMany(ExternalTask::class);
    }

    /**
     * The default tasks that belong to the location.
     * @return BelongsToMany<DefaultTask, $this>
     */
    public function defaultTasks(): BelongsToMany
    {
        return $this->belongsToMany(DefaultTask::class, 'location_default_task');
    }

    /**
     * The requirements that are automatically required for this location.
     * @return BelongsToMany<Requirement, $this>
     */
    public function requiredRequirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'requirement_location');
    }

    /**
     * De planningen die aan deze locatie gekoppeld zijn.
     * @return BelongsToMany<Planning, $this>
     */
    public function plannings(): BelongsToMany
    {
        return $this->belongsToMany(Planning::class, 'location_planning');
    }

    /**
     * Get the full address for the location.
     *
     * @return string
     */
    public function getFullAddressAttribute(): string
    {
        $addressParts = [
            $this->address,
            $this->postal_code,
            $this->city,
        ];

        return trim(implode(' ', array_filter($addressParts)));
    }

    /**
     * Check if all tasks for this location within a planning are completed (review or skipped).
     *
     * @param Planning $planning
     * @return bool
     */
    public function areAllTasksCompletedInPlanning(Planning $planning): bool
    {
        // Get all planning tasks for this location within the given planning
        $planningTasks = $planning->planningTasks()->where(function ($query) {
            $query->where('location_id', $this->id) // Direct location assignment for default tasks
                  ->orWhereHas('task', function ($subQuery) {
                      $subQuery->where('location_id', $this->id); // Backlog tasks
                  });
        })->get();

        // If no tasks for this location, consider it completed
        if ($planningTasks->isEmpty()) {
            return true;
        }

        // Check if all tasks are in review or skipped status
        return $planningTasks->every(function ($planningTask) {
            return in_array($planningTask->status, [
                TaskStatus::REVIEW,
                TaskStatus::SKIPPED,
                TaskStatus::COMPLETED,
            ]);
        });
    }
}
