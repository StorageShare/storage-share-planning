<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningLocationTimer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
     */
    public function planning(): BelongsTo
    {
        return $this->belongsTo(Planning::class);
    }

    /**
     * Get the location that owns the timer.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

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
