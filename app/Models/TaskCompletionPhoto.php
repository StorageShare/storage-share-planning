<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskCompletionPhoto extends Model
{
    protected $fillable = [
        'task_completion_id',
        'file_path',
    ];


    /**
     * @return BelongsTo<TaskCompletion, $this>
     */
    public function taskCompletion(): BelongsTo
    {
        return $this->belongsTo(TaskCompletion::class);
    }
}
