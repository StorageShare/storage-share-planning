<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'external_id',
        'name',
        'address',
        'postal_code',
        'city',
        'last_synced_at',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_synced_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the tasks for the location.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * The default tasks that belong to the location.
     */
    public function defaultTasks(): BelongsToMany
    {
        return $this->belongsToMany(DefaultTask::class, 'location_default_task');
    }

    /**
     * De planningen die aan deze locatie gekoppeld zijn.
     */
    public function plannings(): BelongsToMany
    {
        return $this->belongsToMany(Planning::class, 'location_planning');
    }
}
