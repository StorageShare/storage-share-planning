<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'external_id' => null,
            'name' => $this->faker->company().' - '.$this->faker->city(),
            'address' => $this->faker->streetAddress(),
            'postal_code' => $this->faker->postcode(),
            'city' => $this->faker->city(),
            'description' => $this->faker->sentence(),
            'last_synced_at' => null,
            'type_deur' => $this->faker->randomElement(['overhead deur', 'deurloopt', 'deurloopt-deur', null]),
            'outdoor_safe_code' => $this->faker->randomNumber(5, true),
            'indoor_safe_code' => $this->faker->randomNumber(5, true),
            'outdoor_safe_content' => $this->faker->paragraph(),
            'indoor_safe_content' => $this->faker->paragraph(),
            'intratone_number' => $this->faker->randomNumber(5, true),
            'intratone_multiple_numbers' => $this->faker->randomNumber(5, true),
            'gate_number' => $this->faker->randomNumber(5, true),
            'lift' => $this->faker->randomElement(['Lift', 'Geen lift', null]),
            'total_m2_net' => $this->faker->randomFloat(2, 50, 1000),
            'total_m2_gross' => $this->faker->randomFloat(2, 50, 1000),
            'total_rooms' => $this->faker->numberBetween(1, 100),
            'bv' => $this->faker->randomElement(['BV', 'Geen bv', null]),
        ];
    }
}
