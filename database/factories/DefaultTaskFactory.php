<?php

namespace Database\Factories;

use App\Models\DefaultTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefaultTask>
 */
class DefaultTaskFactory extends Factory
{
    protected $model = DefaultTask::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'feedback_information' => $this->faker->optional()->paragraph(),
            'estimated_time_minutes' => $this->faker->numberBetween(10, 240),
            'is_always_included' => false,
            'applies_to_all_locations' => false,
            'applies_to_lift_locations' => false,
            'applies_to_door_types' => false,
            'door_types' => [],
            'time_calculation_type' => 'simplified',
            'time_per_m2_minutes' => null,
            'base_time_minutes' => null,
            'has_lift_extra_minutes' => null,
            'no_lift_extra_minutes' => null,
            'end_day_action_title' => null,
            'end_day_action_description' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the task applies to all locations.
     */
    public function forAllLocations(): self
    {
        return $this->state(fn (array $attributes) => [
            'applies_to_all_locations' => true,
        ]);
    }

    /**
     * Indicate that the task applies to certain door types.
     */
    public function forDoorTypes(array $doorTypes = ['vergrendeling']): self
    {
        return $this->state(fn (array $attributes) => [
            'applies_to_door_types' => true,
            'door_types' => array_values(array_unique(array_map(fn ($t) => strtolower(trim($t)), $doorTypes))),
        ]);
    }
}
