<?php

namespace Database\Factories;

use App\Models\PlanningTask;
use App\Models\PlanningTaskCompletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanningTaskCompletion>
 */
class PlanningTaskCompletionFactory extends Factory
{
    protected $model = PlanningTaskCompletion::class;

    public function definition(): array
    {
        return [
            'planning_task_id' => PlanningTask::factory(),
            'user_id' => User::factory(),
            'comment' => $this->faker->sentence(),
            'is_fully_completed' => true,
        ];
    }
}
