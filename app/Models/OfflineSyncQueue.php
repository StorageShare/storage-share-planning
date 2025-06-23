<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSyncQueue extends Model
{
    use HasFactory;

    protected $table = 'offline_sync_queue';

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'payload',
        'sync_hash',
        'priority',
        'attempts',
        'last_attempt_at',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'last_attempt_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsSynced(): void
    {
        $this->update([
            'synced_at' => now(),
        ]);
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
        $this->update(['last_attempt_at' => now()]);
    }

    public function scopePending($query)
    {
        return $query->whereNull('synced_at');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc')->orderBy('created_at', 'asc');
    }
} 