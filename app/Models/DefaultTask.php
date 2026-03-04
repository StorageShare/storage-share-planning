<?php

namespace App\Models;

use Database\Factories\DefaultTaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DefaultTask extends Model
{
    /**
     * @use HasFactory<DefaultTaskFactory>
     */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'feedback_information',
        'feedback_owner_name',
        'feedback_emails',
        'is_photo_required',
        'estimated_time_minutes',
        'is_always_included',
        'applies_to_all_locations',
        'applies_to_lift_locations',
        'applies_to_door_types',
        'door_types',
        'end_day_action_title',
        'end_day_action_description',
        'created_by',
        'time_calculation_type',
        'time_per_m2_minutes',
        'base_time_minutes',
        'has_lift_extra_minutes',
        'no_lift_extra_minutes',
    ];

    protected $casts = [
        'is_always_included' => 'boolean',
        'is_photo_required' => 'boolean',
        'applies_to_all_locations' => 'boolean',
        'applies_to_lift_locations' => 'boolean',
        'applies_to_door_types' => 'boolean',
        'door_types' => 'array',
        'time_per_m2_minutes' => 'decimal:2',
        'base_time_minutes' => 'integer',
        'has_lift_extra_minutes' => 'integer',
        'no_lift_extra_minutes' => 'integer',
    ];

    /**
     * Scope a query to only include default tasks that apply to all locations.
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForAllLocations(Builder $query): Builder
    {
        return $query->where('applies_to_all_locations', true);
    }

    /**
     * Scope a query to only include default tasks for a specific location.
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where(function($q) use ($locationId) {
            $q->where('applies_to_all_locations', true)
              ->orWhereHas('locations', function($subQ) use ($locationId) {
                  $subQ->where('location_id', $locationId);
              })
              ->orWhere(function($doorQ) use ($locationId) {
                  $doorQ->where('applies_to_door_types', true)
                       ->whereHas('applicableLocationsByDoorType', function($locationQ) use ($locationId) {
                           $locationQ->where('id', $locationId);
                       });
              });
        });
    }

    /**
     * Scope a query to only include default tasks that apply to door types.
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForDoorTypes(Builder $query): Builder
    {
        return $query->where('applies_to_door_types', true);
    }

    /**
     * Scope a query to only include default tasks for a specific door type.
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForDoorType(Builder $query, string $doorType): Builder
    {
        return $query->where('applies_to_door_types', true)
                    ->whereJsonContains('door_types', strtolower(trim($doorType)));
    }

    /**
     * The locations that belong to the default task.
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_default_task');
    }

    /**
     * Get the planning tasks associated with the default task.
     * @return HasMany<PlanningTask, $this>
     */
    public function planningTasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }

    /**
     * Get the user who created the default task.
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the requirements for the default task.
     * @return BelongsToMany<Requirement, $this>
     */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'default_task_requirements');
    }

    /**
     * Get locations that match the door types for this default task.
     * @return Builder<Location>
     */
    public function applicableLocationsByDoorType(): Builder
    {
        if (!$this->applies_to_door_types || empty($this->door_types)) {
            return Location::whereRaw('1 = 0'); // Return empty query
        }

        $doorTypes = array_map(fn($type) => strtolower(trim($type)), $this->door_types);

        return Location::whereIn(
            DB::raw('LOWER(TRIM(type_deur))'),
            $doorTypes
        );
    }

    /**
     * Check if this default task applies to a given location based on door type.
     */
    public function appliesToLocationByDoorType(Location $location): bool
    {
        if (!$this->applies_to_door_types || empty($this->door_types) || empty($location->type_deur)) {
            return false;
        }

        $locationDoorType = strtolower(trim($location->type_deur));
        $taskDoorTypes = array_map('strtolower', array_map('trim', $this->door_types));

        return in_array($locationDoorType, $taskDoorTypes);
    }

    /**
     * Calculate the estimated time for a specific location.
     */
    public function calculateEstimatedTime(Location $location): int
    {
        if ($this->time_calculation_type === 'advanced') {
            $totalMinutes = (int) ($this->base_time_minutes ?? 0);

            // Calculate based on m2 (using total_m2_net as per user request example)
            if ($this->time_per_m2_minutes && $location->total_m2_net) {
                $totalMinutes += (int) ceil($location->total_m2_net * $this->time_per_m2_minutes);
            }

            // Lift compensation
            if ($location->lift) {
                $totalMinutes += (int) ($this->has_lift_extra_minutes ?? 0);
            } else {
                $totalMinutes += (int) ($this->no_lift_extra_minutes ?? 0);
            }

            return $totalMinutes;
        }

        return (int) ($this->estimated_time_minutes ?? 0);
    }

    /**
     * Get all available door types from locations.
     * @return array<string>
     */
    public static function getAvailableDoorTypes(): array
    {
        return Location::whereNotNull('type_deur')
            ->where('type_deur', '!=', '')
            ->distinct()
            ->pluck('type_deur')
            ->map(fn($type) => trim($type))
            ->filter()
            ->sort()
            ->values()
            ->toArray();
    }
}
