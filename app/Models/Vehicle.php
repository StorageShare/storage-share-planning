<?php

namespace App\Models;

use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property VehicleType $type
 */
class Vehicle extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'license_number',
        'type',
    ];

    protected $casts = [
        'type' => VehicleType::class,
    ];

    /**
     * Normalize license plate on assignment: uppercase and remove hyphens.
     */
    public function setLicenseNumberAttribute(?string $value): void
    {
        $this->attributes['license_number'] = self::normalizeLicenseNumber($value);
    }

    /**
     * Helper used to normalize license numbers consistently.
     */
    public static function normalizeLicenseNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        // Keep only letters and digits, remove hyphens/spaces/other punctuation, then uppercase
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $value);

        return strtoupper($clean ?? '');
    }

    /**
     * Plannings assigned to this vehicle.
     */
    /**
     * @return HasMany<Planning, $this>
     */
    public function plannings(): HasMany
    {
        return $this->hasMany(Planning::class);
    }

    /**
     * Vehicle-specific tasks that can be scheduled for this vehicle.
     */
    /**
     * @return HasMany<VehicleTask, $this>
     */
    public function vehicleTasks(): HasMany
    {
        return $this->hasMany(VehicleTask::class);
    }
}
