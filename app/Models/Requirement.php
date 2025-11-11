<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Requirement extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'requirements';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    /**
     * Get the backlog tasks that use this requirement.
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_requirements');
    }

    /**
     * Get the default tasks that use this requirement.
     */
    public function defaultTasks(): BelongsToMany
    {
        return $this->belongsToMany(DefaultTask::class, 'default_task_requirements');
    }

    /**
     * Get the locations where this requirement is automatically required.
     */
    public function requiredForLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'requirement_location');
    }

    /**
     * Get the user who created the requirement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get requirements that are required for a specific location.
     */
    public function scopeRequiredForLocation($query, $locationId)
    {
        return $query->whereHas('requiredForLocations', function ($q) use ($locationId) {
            $q->where('location_id', $locationId);
        });
    }
}
