<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PlanningTaskCompletionPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'completion_id',
        'file_path',
    ];

    public function planningTaskCompletion(): BelongsTo
    {
        return $this->belongsTo(PlanningTaskCompletion::class, 'completion_id');
    }

    /**
     * Get the full URL to the photo.
     */
    public function getUrlAttribute(): string
    {
        // Always generate URLs from the public disk, since files are stored there
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Append URL accessor to model serialization.
     *
     * @var array
     */
    protected $appends = [
        'url',
    ];
}
