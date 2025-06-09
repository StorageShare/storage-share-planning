<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PlanningTaskPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'planning_task_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    /**
     * Get the planning task that owns the photo.
     */
    public function planningTask(): BelongsTo
    {
        return $this->belongsTo(PlanningTask::class);
    }

    /**
     * Get the full URL to the photo.
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    /**
     * Append URL accessor to model serialization.
     */
    protected $appends = [
        'url',
    ];
}
