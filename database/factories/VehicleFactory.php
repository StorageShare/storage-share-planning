<?php

namespace Database\Factories;

use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company.' Vehicle',
            'license_number' => strtoupper(str_replace('-', '', $this->faker->bothify('??-###-??'))),
            'type' => $this->faker->randomElement([VehicleType::CAR->value, VehicleType::BUS->value]),
        ];
    }
}
