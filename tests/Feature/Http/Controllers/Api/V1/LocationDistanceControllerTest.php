<?php

namespace Feature\Http\Controllers\Api\V1;

use App\Models\Location;
use App\Models\LocationDistance;
use App\Services\LocationDistanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class LocationDistanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_sorted_distances_success(): void
    {
        $from = Location::factory()->create(['name' => 'From A']);

        $mock = $this->mock(LocationDistanceService::class, function ($m) use ($from) {
            $m->shouldReceive('getDistancesForApi')
                ->once()
                ->with($from->id)
                ->andReturn([
                    [
                        'to_location_id' => 123,
                        'location_name' => 'Loc B',
                        'distance_km' => 12.3,
                        'duration_minutes' => 25,
                        'formatted_distance' => '12.3 km',
                        'formatted_duration' => '25 min',
                        'calculated_at' => now()->toISOString(),
                        'is_recent' => true,
                    ],
                ]);
        });

        $resp = $this->getJson("/api/v1/location-distances/{$from->id}/sorted");

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.from_location.id', $from->id)
            ->assertJsonPath('data.from_location.name', $from->name)
            ->assertJsonPath('data.total_count', 1)
            ->assertJsonPath('data.distances.0.location_name', 'Loc B');

        Mockery::close();
    }

    public function test_get_sorted_distances_returns_404_when_location_missing(): void
    {
        $resp = $this->getJson('/api/v1/location-distances/999999/sorted');
        $resp->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_sort_locations_by_distance_success(): void
    {
        $from = Location::factory()->create(['name' => 'From']);
        $a = Location::factory()->create(['name' => 'A']);
        $b = Location::factory()->create(['name' => 'B']);
        $c = Location::factory()->create(['name' => 'C']);

        // Mock service behavior
        $this->mock(LocationDistanceService::class, function ($m) use ($from, $a, $b, $c) {
            $m->shouldReceive('sortLocationsByDistance')
                ->once()
                ->with($from->id, Mockery::on(function ($ids) use ($a, $b, $c) {
                    sort($ids);
                    $expected = [$a->id, $b->id, $c->id];
                    sort($expected);
                    return $ids === $expected; // contains the same IDs (order not important for input)
                }))
                ->andReturn([$b->id, $a->id, $c->id]);

            // Controller enriches response by calling getDistance per id
            foreach ([$b, $a, $c] as $idx => $loc) {
                $distance = new class {
                    public float $distance_km = 10.0;
                    public int $duration_minutes = 20;
                    public string $formatted_distance = '10.0 km';
                    public string $formatted_duration = '20 min';
                };
                $m->shouldReceive('getDistance')
                    ->once()
                    ->with($from->id, $loc->id)
                    ->andReturn($distance);
            }
        });

        $payload = [
            'from_location_id' => $from->id,
            'location_ids' => [$a->id, $b->id, $c->id],
        ];

        $resp = $this->postJson('/api/v1/location-distances/sort', $payload);

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.from_location_id', $from->id)
            ->assertJsonPath('data.sorted_location_ids', [$b->id, $a->id, $c->id])
            ->assertJsonPath('data.total_count', 3)
            ->assertJson(function ($json) use ($b) {
                // Verify enrichment structure exists
                $json->where('data.locations_with_distances.0.location_id', $b->id)
                     ->where('data.locations_with_distances.0.formatted_distance', '10.0 km')
                     ->etc();
            });

        Mockery::close();
    }

    public function test_sort_locations_by_distance_validation_errors(): void
    {
        // Missing fields
        $resp = $this->postJson('/api/v1/location-distances/sort', []);
        $resp->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['from_location_id', 'location_ids']]);
    }

    public function test_get_distance_between_success(): void
    {
        $from = Location::factory()->create(['name' => 'From']);
        $to = Location::factory()->create(['name' => 'To']);

        // create a mock distance object with isRecent method
        $distance = Mockery::mock();
        $distance->distance_km = 5.5;
        $distance->duration_minutes = 12;
        $distance->formatted_distance = '5.5 km';
        $distance->formatted_duration = '12 min';
        $distance->calculated_at = Carbon::now();
        $distance->calculation_method = 'google_maps';
        $distance->shouldReceive('isRecent')->andReturn(true);

        $this->mock(LocationDistanceService::class, function ($m) use ($from, $to, $distance) {
            $m->shouldReceive('getDistance')
                ->once()
                ->with($from->id, $to->id)
                ->andReturn($distance);
        });

        $resp = $this->getJson("/api/v1/location-distances/{$from->id}/to/{$to->id}");

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.from_location.id', $from->id)
            ->assertJsonPath('data.to_location.id', $to->id)
            ->assertJsonPath('data.distance_km', 5.5)
            ->assertJsonPath('data.duration_minutes', 12)
            ->assertJsonPath('data.is_recent', true)
            ->assertJsonPath('data.calculation_method', 'google_maps');

        Mockery::close();
    }

    public function test_get_distance_between_returns_404_when_location_missing(): void
    {
        $existing = Location::factory()->create();
        $resp = $this->getJson("/api/v1/location-distances/{$existing->id}/to/999999");
        $resp->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_get_distance_between_returns_404_when_distance_not_found(): void
    {
        $from = Location::factory()->create();
        $to = Location::factory()->create();

        $this->mock(LocationDistanceService::class, function ($m) use ($from, $to) {
            $m->shouldReceive('getDistance')
                ->once()
                ->with($from->id, $to->id)
                ->andReturn(null);
        });

        $resp = $this->getJson("/api/v1/location-distances/{$from->id}/to/{$to->id}");
        $resp->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Afstand kon niet worden berekend');

        Mockery::close();
    }

    public function test_recalculate_distance_success(): void
    {
        $from = Location::factory()->create(['name' => 'From']);
        $to = Location::factory()->create(['name' => 'To']);

        $distance = Mockery::mock();
        $distance->distance_km = 8.0;
        $distance->duration_minutes = 18;
        $distance->formatted_distance = '8.0 km';
        $distance->formatted_duration = '18 min';
        $distance->calculated_at = Carbon::now();
        $distance->calculation_method = 'google_maps';

        $this->mock(LocationDistanceService::class, function ($m) use ($from, $to, $distance) {
            $m->shouldReceive('getDistance')
                ->once()
                ->with($from->id, $to->id, true)
                ->andReturn($distance);
        });

        $resp = $this->postJson("/api/v1/location-distances/{$from->id}/to/{$to->id}/recalculate");

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Afstand succesvol herberekend')
            ->assertJsonPath('data.distance_km', 8)
            ->assertJsonPath('data.duration_minutes', 18)
            ->assertJsonPath('data.calculation_method', 'google_maps');

        Mockery::close();
    }

    public function test_recalculate_distance_returns_404_when_location_missing(): void
    {
        $existing = Location::factory()->create();
        $resp = $this->postJson("/api/v1/location-distances/{$existing->id}/to/999999/recalculate");
        $resp->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_recalculate_distance_returns_500_when_service_returns_null(): void
    {
        $from = Location::factory()->create();
        $to = Location::factory()->create();

        $this->mock(LocationDistanceService::class, function ($m) use ($from, $to) {
            $m->shouldReceive('getDistance')
                ->once()
                ->with($from->id, $to->id, true)
                ->andReturn(null);
        });

        $resp = $this->postJson("/api/v1/location-distances/{$from->id}/to/{$to->id}/recalculate");
        $resp->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Afstand kon niet worden herberekend');

        Mockery::close();
    }

    public function test_get_cache_stats_success(): void
    {
        Carbon::setTestNow('2025-10-24 12:00:00');

        // Create some locations
        $l1 = Location::factory()->create();
        $l2 = Location::factory()->create();
        $l3 = Location::factory()->create();

        // Create some cached distances
        LocationDistance::create([
            'from_location_id' => $l1->id,
            'to_location_id' => $l2->id,
            'distance_km' => 10.0,
            'duration_minutes' => 20,
            'calculated_at' => now()->subHours(2), // recent
            'calculation_method' => 'google_maps',
            'api_response' => ['x' => 1],
        ]);

        LocationDistance::create([
            'from_location_id' => $l2->id,
            'to_location_id' => $l1->id,
            'distance_km' => 10.0,
            'duration_minutes' => 20,
            'calculated_at' => now()->subDays(400), // old
            'calculation_method' => 'google_maps',
            'api_response' => ['x' => 1],
        ]);

        $resp = $this->getJson('/api/v1/location-distances/stats');

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_cached_distances',
                    'recent_distances',
                    'old_distances',
                    'total_locations',
                    'max_possible_distances',
                    'coverage_percentage',
                    'cache_stats' => ['recent_threshold_hours', 'old_threshold_days'],
                ]
            ])
            ->assertJsonPath('data.total_locations', 3)
            ->assertJsonPath('data.total_cached_distances', 2)
            ->assertJsonPath('data.recent_distances', 1)
            ->assertJsonPath('data.old_distances', 1)
            ->assertJsonPath('data.max_possible_distances', 3 * 2);

        Carbon::setTestNow();
    }
}
