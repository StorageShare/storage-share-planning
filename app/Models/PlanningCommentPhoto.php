<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PlanningCommentPhoto extends Model
{
    protected $fillable = [
        'planning_comment_id',
        'location_id',
        'file_path',
        'room',
    ];

    protected $appends = [
        'url',
    ];

    /**
     * @return BelongsTo<PlanningComment, $this>
     */
    public function planningComment(): BelongsTo
    {
        return $this->belongsTo(PlanningComment::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return string
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
