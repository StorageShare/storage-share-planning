<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'deadline' => null,
            'estimated_time_minutes' => null,
            'priority' => TaskPriority::NORMAL->value,
            'status' => TaskStatus::OPEN->value,
        ];
    }

    public function concept(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::CONCEPT->value,
        ]);
    }

    public function open(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::OPEN->value,
        ]);
    }

    public function inProgress(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::IN_PROGRESS->value,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::COMPLETED->value,
        ]);
    }
}
