<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultVehicleTask extends Model
{
    protected $fillable = [
        'title',
        'description',
        'estimated_time_minutes',
        'active',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'estimated_time_minutes' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
