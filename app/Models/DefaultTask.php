<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DefaultTask extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'estimated_time_minutes',
        'applies_to_all_locations',
        'applies_to_door_types',
        'door_types',
        'created_by',
        'end_day_action_title',
        'end_day_action_description',
    ];

    protected $casts = [
        'applies_to_all_locations' => 'boolean',
        'applies_to_door_types' => 'boolean',
        'door_types' => 'array',
    ];

    /**
     * Scope a query to only include default tasks that apply to all locations.
     */
    public function scopeForAllLocations($query)
    {
        return $query->where('applies_to_all_locations', true);
    }

    /**
     * Scope a query to only include default tasks for a specific location.
     */
    public function scopeForLocation($query, $locationId)
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
     */
    public function scopeForDoorTypes($query)
    {
        return $query->where('applies_to_door_types', true);
    }

    /**
     * Scope a query to only include default tasks for a specific door type.
     */
    public function scopeForDoorType($query, $doorType)
    {
        return $query->where('applies_to_door_types', true)
                    ->whereJsonContains('door_types', strtolower(trim($doorType)));
    }

    /**
     * The locations that belong to the default task.
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_default_task');
    }

    /**
     * Get the planning tasks associated with the default task.
     */
    public function planningTasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }

    /**
     * Get the user who created the default task.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the benodigdheden for the default task.
     */
    public function benodigdheden(): BelongsToMany
    {
        return $this->belongsToMany(Benodigdheid::class, 'default_task_benodigdheden');
    }

    /**
     * Get locations that match the door types for this default task.
     */
    public function applicableLocationsByDoorType()
    {
        if (!$this->applies_to_door_types || empty($this->door_types)) {
            return Location::whereRaw('1 = 0'); // Return empty query
        }

        $doorTypes = array_map('strtolower', array_map('trim', $this->door_types));
        
        return Location::whereRaw('LOWER(TRIM(type_deur)) IN (' . 
            implode(',', array_fill(0, count($doorTypes), '?')) . ')', $doorTypes);
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
     * Get all available door types from locations.
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
