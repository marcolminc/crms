<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Customer,
            'phone' => '07'.fake()->numerify('########'),
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Customer,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    public function counterAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::CounterAgent,
        ]);
    }

    public function fleetStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::FleetStaff,
        ]);
    }

    public function finance(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Finance,
        ]);
    }

    public function branchManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::BranchManager,
        ]);
    }

    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Driver,
        ]);
    }
}
