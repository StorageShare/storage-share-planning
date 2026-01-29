<?php

namespace Database\Factories;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'google_id' => null,
            // Default role for new users
            'role' => Role::GEBRUIKER->value,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Set the user's role to admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::ADMIN->value,
        ]);
    }

    /**
     * Set the user's role to facilities coordinator.
     */
    public function facilitiesCoordinator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::FACILITIES_COORDINATOR->value,
        ]);
    }

    /**
     * Set the user's role to customer service.
     */
    public function customerService(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::CUSTOMER_SERVICE->value,
        ]);
    }

    /**
     * Set the user's role to algemeen medewerker.
     */
    public function algemeenMedewerker(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::ALGEMEEN_MEDEWERKER->value,
        ]);
    }

    /**
     * Set the user's role to gebruiker (default).
     */
    public function gebruiker(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::GEBRUIKER->value,
        ]);
    }
}
