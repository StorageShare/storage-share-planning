<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndChecklistItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'planning_id',
        'location_id',
        'type',
        'requirement_id',
        'title',
        'description',
        'photo_path',
        'uploaded_by',
        'uploaded_at',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'reviewed_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Get the planning that owns the checklist item.
     */
    public function planning(): BelongsTo
    {
        return $this->belongsTo(Planning::class);
    }

    /**
     * Get the requirement (material) associated with this item (if type is 'material').
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }

    /**
     * Get the location associated with this checklist item.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who uploaded the photo for this item.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who reviewed this item.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope om alleen material items te krijgen.
     */
    public function scopeMaterials($query)
    {
        return $query->where('type', 'material');
    }

    /**
     * Scope om alleen end action items te krijgen.
     */
    public function scopeEndActions($query)
    {
        return $query->where('type', 'end_action');
    }

    /**
     * Scope om alleen items met bepaalde status te krijgen.
     */
    public function scopeWithStatus($query, string $status)
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
}
