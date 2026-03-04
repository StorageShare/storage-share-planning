<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Planning $planning
 * @property-read Collection<int, EndChecklistItemPhoto> $photos
 * @property int|null $item_count
 * @property Collection<int, EndChecklistItem>|null $all_items
 */
class EndChecklistItem extends Model
{
    protected $attributes = [
        'status' => 'open',
    ];

    protected $fillable = [
        'title',
        'planning_id',
        'location_id',
        'type',
        'requirement_id',
        'title',
        'description',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
        // Deprecated: kept for backward compatibility; use photos relation instead
        'photo_path',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Get the planning that owns the checklist item.
     *
     * @return BelongsTo<Planning, $this>
     */
    public function planning(): BelongsTo
    {
        return $this->belongsTo(Planning::class);
    }

    /**
     * Get the requirement (material) associated with this item (if type is 'material').
     *
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }

    /**
     * Get the location associated with this checklist item.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who uploaded the photo for this item.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who reviewed this item.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Photos uploaded for this checklist item (multiple).
     *
     * @return HasMany<EndChecklistItemPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(EndChecklistItemPhoto::class, 'end_checklist_item_id');
    }

    /**
     * Scope om alleen material items te krijgen.
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeMaterials(Builder $query): Builder
    {
        return $query->where('type', 'material');
    }

    /**
     * Scope om alleen end action items te krijgen.
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeEndActions(Builder $query): Builder
    {
        return $query->where('type', 'end_action');
    }

    /**
     * Scope om alleen items met bepaalde status te krijgen.
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Check of het item is goedgekeurd.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check of het item is afgewezen.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check of het item nog wacht op beoordeling.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check of het item nog open staat (nog niet ingediend).
     */
    public function isOpen(): bool
    {
        return $this->status === 'open' || empty($this->status);
    }
}
