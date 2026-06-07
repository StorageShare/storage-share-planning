<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ExternalTask;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalTask>
 */
class ExternalTaskFactory extends Factory
{
    protected $model = ExternalTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'feedback_information' => $this->faker->optional()->paragraph(),
            'external_deadline_at' => null,
            'estimated_time_minutes' => null,
            'status' => TaskStatus::IN_REVIEW->value,
            'priority' => TaskPriority::NORMAL->value,
        ];
    }
}
