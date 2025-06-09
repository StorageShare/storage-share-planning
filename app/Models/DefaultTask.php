<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PlanningTask;

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
        'created_by',
    ];

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
}
