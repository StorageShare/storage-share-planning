<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Planning;
use App\Models\PlanningTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanningTask>
 */
class PlanningTaskFactory extends Factory
{
    protected $model = PlanningTask::class;

    public function definition(): array
    {
        return [
            'planning_id' => Planning::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(8),
            'status' => TaskStatus::OPEN,
        ];
    }
}
