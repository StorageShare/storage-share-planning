<?php

namespace Feature\Http\Controllers\Api\V1;

use App\Models\Location;
use App\Services\TravelTimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TravelTimeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_success(): void
    {
        $payload = [
            'origin' => 'Amsterdam, NL',
            'destination' => 'Utrecht, NL',
            'mode' => 'driving',
        ];

        $expected = [
            'duration_minutes' => 35,
            'distance_km' => 42.5,
            'duration_text' => '35 min',
            'distance_text' => '42.5 km',
        ];

        $this->mock(TravelTimeService::class, function ($m) use ($payload, $expected) {
            $m->shouldReceive('calculateTravelTime')
                ->once()
                ->with($payload['origin'], $payload['destination'], $payload['mode'])
                ->andReturn($expected);
        });

        $resp = $this->postJson('/api/v1/travel-times/calculate', $payload);

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.duration_minutes', 35)
            ->assertJsonPath('data.distance_km', 42.5)
            ->assertJsonPath('data.duration_text', '35 min')
            ->assertJsonPath('data.distance_text', '42.5 km');

        Mockery::close();
    }

    public function test_calculate_validation_errors(): void
    {
        // Missing required fields
        $resp = $this->postJson('/api/v1/travel-times/calculate', []);
        $resp->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['origin', 'destination']]);

        // Invalid mode
        $resp2 = $this->postJson('/api/v1/travel-times/calculate', [
            'origin' => 'A',
            'destination' => 'B',
            'mode' => 'flying',
        ]);
        $resp2->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['mode']]);
    }

    public function test_calculate_handles_exception(): void
    {
        $payload = [
            'origin' => 'Amsterdam',
            'destination' => 'Rotterdam',
        ];

        $this->mock(TravelTimeService::class, function ($m) use ($payload) {
            $m->shouldReceive('calculateTravelTime')
                ->once()
                ->with($payload['origin'], $payload['destination'], 'driving')
                ->andThrow(new \Exception('boom'));
        });

        $resp = $this->postJson('/api/v1/travel-times/calculate', $payload);
        $resp->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Fout bij berekenen reistijd');

        Mockery::close();
    }

    public function test_calculate_sequence_success(): void
    {
        $l1 = Location::factory()->create(['name' => 'Loc 1']);
        $l2 = Location::factory()->create(['name' => 'Loc 2']);
        $l3 = Location::factory()->create(['name' => 'Loc 3']);

        $payload = [
            'location_ids' => [$l1->id, $l3->id, $l2->id], // intentionally unordered creation
            'start_address' => 'HQ Address, City',
            'mode' => 'walking',
        ];

        $expected = [
            'segments' => [
                ['from' => 'HQ', 'to' => 'Loc 1', 'duration_minutes' => 5, 'distance_km' => 0.4, 'index' => 0],
                ['from' => 'Loc 1', 'to' => 'Loc 3', 'duration_minutes' => 7, 'distance_km' => 0.6, 'index' => 1],
                ['from' => 'Loc 3', 'to' => 'Loc 2', 'duration_minutes' => 10, 'distance_km' => 0.8, 'index' => 2],
                ['from' => 'Loc 2', 'to' => 'HQ', 'duration_minutes' => 12, 'distance_km' => 1.0, 'index' => 'return', 'is_return' => true],
            ],
            'total_duration_minutes' => 34,
            'total_duration_formatted' => '34 min',
        ];

        $this->mock(TravelTimeService::class, function ($m) use ($payload, $l1, $l2, $l3, $expected) {
            $m->shouldReceive('calculateTravelTimesForSequence')
                ->once()
                ->with(Mockery::on(function ($locations) use ($l1, $l3, $l2) {
                    // Expect the order to match the provided location_ids
                    if (! is_array($locations) || count($locations) !== 3) {
                        return false;
                    }

                    return $locations[0]->id === $l1->id
                        && $locations[1]->id === $l3->id
                        && $locations[2]->id === $l2->id;
                }), $payload['start_address'], $payload['mode'])
                ->andReturn($expected);
        });

        $resp = $this->postJson('/api/v1/travel-times/sequence', $payload);

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_duration_minutes', 34)
            ->assertJsonPath('data.segments.0.duration_minutes', 5)
            ->assertJsonPath('data.segments.3.is_return', true);

        Mockery::close();
    }

    public function test_calculate_sequence_validation_errors(): void
    {
        // Missing location_ids
        $resp = $this->postJson('/api/v1/travel-times/sequence', []);
        $resp->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['location_ids']]);

        // Invalid mode
        $l = Location::factory()->create();
        $resp2 = $this->postJson('/api/v1/travel-times/sequence', [
            'location_ids' => [$l->id],
            'mode' => 'flying',
        ]);
        $resp2->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['mode']]);

        // Non-existent ID should fail exists rule
        $resp3 = $this->postJson('/api/v1/travel-times/sequence', [
            'location_ids' => [999999],
        ]);
        $resp3->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['location_ids.0']]);
    }

    public function test_calculate_sequence_handles_exception(): void
    {
        $l1 = Location::factory()->create();
        $payload = [
            'location_ids' => [$l1->id],
        ];

        $this->mock(TravelTimeService::class, function ($m) {
            $m->shouldReceive('calculateTravelTimesForSequence')
                ->once()
                ->with(Mockery::type('array'), null, 'driving')
                ->andThrow(new \Exception('kapot'));
        });

        $resp = $this->postJson('/api/v1/travel-times/sequence', $payload);
        $resp->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Fout bij berekenen reistijden');

        Mockery::close();
    }
}
