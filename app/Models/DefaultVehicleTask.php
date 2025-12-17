<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultVehicleTask extends Model
{
    use HasFactory;

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
