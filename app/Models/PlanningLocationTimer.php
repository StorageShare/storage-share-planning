<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $location_id
 * @property string|null $location_type
 * @property int|null $total_duration_seconds
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $ended_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read Planning $planning
 * @property-read Location|null $location
 */
class PlanningLocationTimer extends Model
{

    protected $fillable = [
        'planning_id',
        'location_id',
        'location_type',
        'started_at',
        'ended_at',
        'total_duration_seconds',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the planning that owns the timer.
     * @return BelongsTo<Planning, $this>
     */
    public function planning(): BelongsTo
    {
        return $this->belongsTo(Planning::class);
    }

    /**
     * Get the location that owns the timer.
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return string
     */
    public function label(): string
    {
        switch ($this->location_type) {
            case 'travel':
                return 'Reistijd';
            case 'location':
                return 'Op locatie';
            case 'travel_back':
                return 'Terugreistijd';
            default:
                return 'Onbekend';
        }
    }
}
