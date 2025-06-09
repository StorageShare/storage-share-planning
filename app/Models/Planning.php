<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PlanningTask; // Import PlanningTask
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Planning extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'planned_date',
        'notes',
        'status',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'planned_date' => 'datetime',
    ];

    /**
     * De locaties die bij deze planning horen.
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_planning');
    }

    /**
     * Get the tasks for the planning.
     */
    public function planningTasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }

    /**
     * The users that belong to the planning.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get the user who created the planning.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
