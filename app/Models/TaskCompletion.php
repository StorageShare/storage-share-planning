<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskCompletion extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'comment',
        'is_fully_completed',
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TaskCompletionPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(TaskCompletionPhoto::class);
    }
}
