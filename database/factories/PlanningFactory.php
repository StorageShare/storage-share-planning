<?php

namespace Database\Factories;

use App\Models\Planning;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Planning>
 */
class PlanningFactory extends Factory
{
    protected $model = Planning::class;

    public function definition(): array
    {
        return [
            'planned_date' => $this->faker->dateTimeBetween('-1 week', '+1 week'),
            'notes' => $this->faker->sentence(),
            'status' => 'open',
            'created_by' => User::factory(),
            'start_address' => $this->faker->address(),
            'start_time' => null,
            'travel_time_distributed_at' => null,
            'vehicle_id' => null,
        ];
    }
}
