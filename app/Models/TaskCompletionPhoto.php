<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskCompletionPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_completion_id',
        'file_path',
    ];

    public function taskCompletion(): BelongsTo
    {
        return $this->belongsTo(TaskCompletion::class);
    }
}
