<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleTask extends Model
{
    protected $fillable = [
        'vehicle_id',
        'title',
        'description',
        'estimated_time_minutes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'estimated_time_minutes' => 'integer',
    ];

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PlanningTask, $this>
     */
    public function planningTasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }
}
