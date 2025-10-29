<?php

namespace Feature\Models;

use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningLocationTimer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningTravelDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_even_distribution_of_travel_time_across_locations_and_idempotent(): void
    {
        $planning = Planning::factory()->create();

        // Create 3 locations and attach to planning in order
        $l1 = Location::factory()->create();
        $l2 = Location::factory()->create();
        $l3 = Location::factory()->create();
        $planning->locations()->attach([$l1->id => ['sort_order' => 0], $l2->id => ['sort_order' => 1], $l3->id => ['sort_order' => 2]]);

        // Create travel timers totaling 100 seconds (e.g., 30 + 30 + 40)
        PlanningLocationTimer::create([
            'planning_id' => $planning->id,
            'location_id' => $l1->id,
            'location_type' => 'travel', // to l1
            'total_duration_seconds' => 30,
        ]);
        PlanningLocationTimer::create([
            'planning_id' => $planning->id,
            'location_id' => $l2->id,
            'location_type' => 'travel', // to l2
            'total_duration_seconds' => 30,
        ]);
        PlanningLocationTimer::create([
            'planning_id' => $planning->id,
            'location_id' => null,
            'location_type' => 'travel_back',
            'total_duration_seconds' => 40,
        ]);

        // Create base location timers (work time) with 10 seconds each
        $t1 = PlanningLocationTimer::create([
            'planning_id' => $planning->id,
            'location_id' => $l1->id,
            'location_type' => 'location',
            'total_duration_seconds' => 10,
        ]);
        $t2 = PlanningLocationTimer::create([
            'planning_id' => $planning->id,
            'location_id' => $l2->id,
            'location_type' => 'location',
            'total_duration_seconds' => 10,
        ]);
        $t3 = PlanningLocationTimer::create([
            'planning_id' => $planning->id,
            'location_id' => $l3->id,
            'location_type' => 'location',
            'total_duration_seconds' => 10,
        ]);

        // Trigger distribution
        $planning->load('locations', 'locationTimers');
        $planning->distributeTravelTimeToLocationsIfNeeded();
        $planning->refresh();
        $t1->refresh();
        $t2->refresh();
        $t3->refresh();

        // 100 seconds total across 3 locations => 33, 33, 34 (remainder to last index)
        $this->assertEquals(10 + 33, $t1->total_duration_seconds);
        $this->assertEquals(10 + 33, $t2->total_duration_seconds);
        $this->assertEquals(10 + 34, $t3->total_duration_seconds);
        $this->assertNotNull($planning->travel_time_distributed_at);

        // Second call should be idempotent (no changes)
        $planning->distributeTravelTimeToLocationsIfNeeded();
        $t1->refresh();
        $t2->refresh();
        $t3->refresh();
        $this->assertEquals(43, $t1->total_duration_seconds);
        $this->assertEquals(43, $t2->total_duration_seconds);
        $this->assertEquals(44, $t3->total_duration_seconds);
    }
}
