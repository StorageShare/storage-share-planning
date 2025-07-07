<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class LocationDistance extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_location_id',
        'to_location_id',
        'distance_km',
        'duration_minutes',
        'calculated_at',
        'calculation_method',
        'api_response',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'duration_minutes' => 'integer',
        'calculated_at' => 'datetime',
        'api_response' => 'array',
    ];

    /**
     * Relatie naar de van-locatie
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * Relatie naar de naar-locatie
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * Scope voor het vinden van afstand tussen specifieke locaties
     */
    public function scopeBetweenLocations($query, $fromLocationId, $toLocationId)
    {
        return $query->where('from_location_id', $fromLocationId)
                    ->where('to_location_id', $toLocationId);
    }

    /**
     * Scope voor recente berekeningen
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('calculated_at', '>=', Carbon::now()->subHours($hours));
    }

    /**
     * Haal afstand op tussen twee locaties (bidirectioneel)
     * Probeert beide richtingen en geeft de gevonden afstand terug
     */
    public static function getDistance($fromLocationId, $toLocationId): ?self
    {
        // Probeer eerst de directe richting
        $distance = self::betweenLocations($fromLocationId, $toLocationId)->first();
        
        // Als niet gevonden, probeer de omgekeerde richting
        if (!$distance) {
            $distance = self::betweenLocations($toLocationId, $fromLocationId)->first();
        }

        return $distance;
    }

    /**
     * Haal alleen de afstand in kilometers op
     */
    public static function getDistanceKm($fromLocationId, $toLocationId): ?float
    {
        $distance = self::getDistance($fromLocationId, $toLocationId);
        return $distance?->distance_km;
    }

    /**
     * Haal alleen de reistijd in minuten op
     */
    public static function getDurationMinutes($fromLocationId, $toLocationId): ?int
    {
        $distance = self::getDistance($fromLocationId, $toLocationId);
        return $distance?->duration_minutes;
    }

    /**
     * Sla een nieuwe afstand op (of update bestaande)
     */
    public static function storeDistance(
        $fromLocationId,
        $toLocationId,
        $distanceKm,
        $durationMinutes,
        $calculationMethod = 'google_maps',
        $apiResponse = null
    ): self {
        return self::updateOrCreate(
            [
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
            ],
            [
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'calculated_at' => Carbon::now(),
                'calculation_method' => $calculationMethod,
                'api_response' => $apiResponse,
            ]
        );
    }

    /**
     * Haal alle afstanden op vanaf een specifieke locatie, gesorteerd op afstand
     */
    public static function getDistancesFrom($fromLocationId, $sortBy = 'distance_km')
    {
        return self::where('from_location_id', $fromLocationId)
                  ->with('toLocation')
                  ->orderBy($sortBy)
                  ->get();
    }

    /**
     * Haal alle afstanden op naar een specifieke locatie
     */
    public static function getDistancesTo($toLocationId, $sortBy = 'distance_km')
    {
        return self::where('to_location_id', $toLocationId)
                  ->with('fromLocation')
                  ->orderBy($sortBy)
                  ->get();
    }

    /**
     * Check of een afstand recentelijk is berekend
     */
    public function isRecent($hours = 24): bool
    {
        if (!$this->calculated_at) {
            return false;
        }

        return $this->calculated_at->greaterThan(Carbon::now()->subHours($hours));
    }

    /**
     * Formatteer afstand voor weergave
     */
    public function getFormattedDistanceAttribute(): string
    {
        if (!$this->distance_km) {
            return 'Onbekend';
        }

        return number_format($this->distance_km, 1) . ' km';
    }

    /**
     * Formatteer reistijd voor weergave
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_minutes) {
            return 'Onbekend';
        }

        $hours = intval($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return "{$hours}u {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Bulk insert van afstanden (voor seeding/import)
     */
    public static function bulkStore(array $distances): int
    {
        $now = Carbon::now();
        $data = collect($distances)->map(function ($distance) use ($now) {
            return [
                'from_location_id' => $distance['from_location_id'],
                'to_location_id' => $distance['to_location_id'],
                'distance_km' => $distance['distance_km'],
                'duration_minutes' => $distance['duration_minutes'],
                'calculated_at' => $now,
                'calculation_method' => $distance['calculation_method'] ?? 'google_maps',
                'api_response' => $distance['api_response'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->toArray();

        return self::insert($data);
    }
}
