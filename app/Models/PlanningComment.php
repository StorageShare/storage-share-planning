<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanningComment extends Model
{
    protected $fillable = [
        'planning_id',
        'location_id',
        'user_id',
        'comment',
    ];

    protected $appends = [
        'photos_json',
    ];

    /**
     * Get the photos for JSON.
     */
    public function getPhotosJsonAttribute()
    {
        return $this->photos->map(fn($p) => [
            'id' => $p->id,
            'url' => $p->url
        ]);
    }

    /**
     * Get the planning associated with the comment.
     * @return BelongsTo<Planning, $this>
     */
    public function planning(): BelongsTo
    {
        return $this->belongsTo(Planning::class);
    }

    /**
     * Get the location associated with the planning comment.
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who created the planning comment.
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the photos associated with the planning comment.
     * @return HasMany<PlanningCommentPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(PlanningCommentPhoto::class);
    }
}
