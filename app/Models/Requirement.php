<?php

namespace App\Models;

use Database\Factories\RequirementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Requirement extends Model
{
    /**
     * @use HasFactory<RequirementFactory>
     */
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
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    /**
     * Get the backlog tasks that use this requirement.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_requirements');
    }

    /**
     * Get the default tasks that use this requirement.
     *
     * @return BelongsToMany<DefaultTask, $this>
     */
    public function defaultTasks(): BelongsToMany
    {
        return $this->belongsToMany(DefaultTask::class, 'default_task_requirements');
    }

    /**
     * Get the locations where this requirement is automatically required.
     *
     * @return BelongsToMany<Location, $this>
     */
    public function requiredForLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'requirement_location');
    }

    /**
     * Get the user who created the requirement.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get requirements that are required for a specific location.
     *
     * @param  Builder<Requirement>  $query
     * @return Builder<Requirement>
     */
    public function scopeRequiredForLocation(Builder $query, int $locationId): Builder
    {
        return $query->whereHas('requiredForLocations', function (Builder $q) use ($locationId): void {
            $q->where('location_id', $locationId);
        });
    }
}
