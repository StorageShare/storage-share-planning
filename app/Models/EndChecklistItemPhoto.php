<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndChecklistItemPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'end_checklist_item_id',
        'file_path',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    protected $appends = ['url'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(EndChecklistItem::class, 'end_checklist_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        // Use route helper to serve via media route (avoids direct storage path issues)

        return route('media', ['path' => $this->file_path]);
    }
}
