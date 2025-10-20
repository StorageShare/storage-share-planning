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
            'bv' => $this->faker->randomElement(['BV', 'Geen bv', null]),
        ];
    }
}
