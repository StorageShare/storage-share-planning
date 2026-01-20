<?php

namespace Tests\Unit;

use App\Models\DefaultTask;
use App\Models\Location;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DefaultTaskTimeCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simplified_calculation_returns_estimated_time()
    {
        $defaultTask = DefaultTask::factory()->create([
            'time_calculation_type' => 'simplified',
            'estimated_time_minutes' => 30,
        ]);

        $location = Location::factory()->create();

        $this->assertEquals(30, $defaultTask->calculateEstimatedTime($location));
    }

    public function test_advanced_calculation_with_m2()
    {
        $defaultTask = DefaultTask::factory()->create([
            'time_calculation_type' => 'advanced',
            'base_time_minutes' => 10,
            'time_per_m2_minutes' => 0.15, // 15 min per 100m2 = 0.15 min per m2
        ]);

        // 100 m2
        $location = Location::factory()->create([
            'total_m2_net' => 100,
            'lift' => false,
        ]);

        // 10 (base) + (100 * 0.15) = 10 + 15 = 25
        $this->assertEquals(25, $defaultTask->calculateEstimatedTime($location));
    }

    public function test_advanced_calculation_with_lift()
    {
        $defaultTask = DefaultTask::factory()->create([
            'time_calculation_type' => 'advanced',
            'base_time_minutes' => 10,
            'has_lift_extra_minutes' => 5,
            'no_lift_extra_minutes' => 20,
        ]);

        $locationWithLift = Location::factory()->create(['lift' => true]);
        $locationWithoutLift = Location::factory()->create(['lift' => false]);

        // 10 + 5 = 15
        $this->assertEquals(15, $defaultTask->calculateEstimatedTime($locationWithLift));
        // 10 + 20 = 30
        $this->assertEquals(30, $defaultTask->calculateEstimatedTime($locationWithoutLift));
    }

    public function test_advanced_calculation_combined()
    {
        $defaultTask = DefaultTask::factory()->create([
            'time_calculation_type' => 'advanced',
            'base_time_minutes' => 5,
            'time_per_m2_minutes' => 0.1,
            'no_lift_extra_minutes' => 15,
        ]);

        $location = Location::factory()->create([
            'total_m2_net' => 200,
            'lift' => false,
        ]);

        // 5 (base) + (200 * 0.1) + 15 (no lift) = 5 + 20 + 15 = 40
        $this->assertEquals(40, $defaultTask->calculateEstimatedTime($location));
    }
}
