<?php

namespace App\Models;

use App\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TaskPhoto; // Import TaskPhoto
use App\Models\PlanningTask; // Import PlanningTask

class Task extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'location_id',
        'title',
        'description',
        'deadline',
        'estimated_time_minutes',
        'status',
        'priority',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deadline' => 'date',
        'estimated_time_minutes' => 'integer',
        'priority' => TaskPriority::class,
    ];

    /**
     * Get the location that owns the task.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the photos for the task.
     */
    public function taskPhotos(): HasMany
    {
        return $this->hasMany(TaskPhoto::class);
    }

    /**
     * Get the planning tasks associated with this task.
     */
    public function planningTasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }

    /**
     * Get the user who created the task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
