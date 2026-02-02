<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'title',
        'description',
        'feedback_information',
        'external_deadline_at',
        'estimated_time_minutes',
        'status',
        'priority',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'external_deadline_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ExternalTaskComment::class);
    }
}
