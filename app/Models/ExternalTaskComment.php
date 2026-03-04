<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalTaskComment extends Model
{
    protected $fillable = [
        'external_task_id',
        'user_id',
        'comment',
    ];

    /**
     * @return BelongsTo<ExternalTask, $this>
     */
    public function externalTask(): BelongsTo
    {
        return $this->belongsTo(ExternalTask::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
