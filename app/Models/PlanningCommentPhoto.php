<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PlanningCommentPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'planning_comment_id',
        'file_path',
    ];

    protected $appends = [
        'url',
    ];

    public function planningComment(): BelongsTo
    {
        return $this->belongsTo(PlanningComment::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
