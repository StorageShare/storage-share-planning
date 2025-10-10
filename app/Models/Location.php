<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'external_id',
        'name',
        'address',
        'postal_code',
        'city',
        'last_synced_at',
        'deleted_at',
        'zip_code',
        'latitude',
        'longitude',
        'google_place_id',
        'outdoor_safe_code',
        'indoor_safe_code',
        'outdoor_safe_content',
        'indoor_safe_content',
        'intratone_number',
        'intratone_multiple_numbers',
        'gate_number',
        'lift',
        'bv',
        'type_deur',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_synced_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the tasks for the location.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * The default tasks that belong to the location.
     */
    public function defaultTasks(): BelongsToMany
    {
        return $this->belongsToMany(DefaultTask::class, 'location_default_task');
    }

    /**
     * The benodigdheden that are automatically required for this location.
     */
    public function requiredBenodigdheden(): BelongsToMany
    {
        return $this->belongsToMany(Benodigdheid::class, 'benodigdheid_location');
    }

    /**
     * De planningen die aan deze locatie gekoppeld zijn.
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
     * @param \App\Models\Planning $planning
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
                \App\Enums\TaskStatus::REVIEW,
                \App\Enums\TaskStatus::SKIPPED,
                \App\Enums\TaskStatus::COMPLETED,
            ]);
        });
    }
}
