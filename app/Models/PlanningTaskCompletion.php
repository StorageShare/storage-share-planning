<?php

namespace App\Models;

use Database\Factories\PlanningTaskCompletionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanningTaskCompletion extends Model
{
    /**
     * @use HasFactory<PlanningTaskCompletionFactory>
     */
    use HasFactory;

    protected $fillable = [
        'planning_task_id',
        'user_id',
        'comment',
        'is_fully_completed',
        'review_notes',
        'reviewed_at',
        'review_outcome',
        'reviewed_by',
        // Allow explicit timestamp setting in tests and seeders
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PlanningTask, $this>
     */
    public function planningTask(): BelongsTo
    {
        return $this->belongsTo(PlanningTask::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<PlanningTaskCompletionPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(PlanningTaskCompletionPhoto::class, 'completion_id');
    }
}
