<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Task>
 */
class
TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'feedback_information' => $this->faker->optional()->paragraph(),
            'deadline' => null,
            'estimated_time_minutes' => null,
            'priority' => TaskPriority::NORMAL->value,
            'status' => TaskStatus::OPEN->value,
            'review_notes' => null,
            'created_by' => User::factory(),
            'end_day_action_title' => null,
            'end_day_action_description' => null,
            'is_recurring' => false,
            'recurring_interval_type' => null,
            'recurring_interval_value' => null,
            'parent_recurring_task_id' => null,
        ];
    }

    public function forLocation(Model $location): self
    {
        return $this->state(fn (array $attributes) => [
            'location_id' => $location,
        ]);
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

    public function rejected(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::REJECTED->value,
        ]);
    }

    public function review(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::REVIEW->value,
        ]);
    }

    public function low(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TaskPriority::LOW->value,
        ]);
    }

    public function normal(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TaskPriority::NORMAL->value,
        ]);
    }

    public function high(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TaskPriority::HIGH->value,
        ]);
    }
}
