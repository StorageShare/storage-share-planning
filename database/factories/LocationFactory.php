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
            'sync_external_id' => null,
            'name' => $this->faker->company().' - '.$this->faker->city(),
            'last_synced_at' => null,
            'type_deur' => $this->faker->randomElement(['overhead deur', 'deurloopt', 'deurloopt-deur', null]),
            'lift' => $this->faker->optional()->boolean(),
            'latitude' => $this->faker->optional()->latitude(50, 54),
            'longitude' => $this->faker->optional()->longitude(3, 7),
            'total_m2_net' => $this->faker->randomFloat(2, 50, 1000),
            'total_m2_gross' => $this->faker->randomFloat(2, 50, 1000),
            'total_rooms' => $this->faker->numberBetween(1, 100),
            'bv' => $this->faker->randomElement(['BV', 'Geen bv', null]),
        ];
    }
}
