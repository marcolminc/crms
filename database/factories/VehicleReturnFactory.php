<?php

namespace Database\Factories;

use App\Enums\VehicleCondition;
use App\Models\Rental;
use App\Models\User;
use App\Models\VehicleReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleReturn>
 */
class VehicleReturnFactory extends Factory
{
    protected $model = VehicleReturn::class;

    public function definition(): array
    {
        return [
            // rental_id is UNIQUE; always point at a freshly created rental.
            'rental_id' => Rental::factory()->completed(),
            // vehicle_id must match the rental's vehicle.
            'vehicle_id' => fn (array $attributes) => Rental::find($attributes['rental_id'])->vehicle_id,
            'returned_at' => now(),
            'condition' => VehicleCondition::Good,
            'fuel_level' => fake()->randomElement(['full', 'three_quarter', 'half']),
            'mileage_at_return' => fake()->numberBetween(30000, 200000),
            'damage_notes' => null,
            'additional_charges' => 0,
            'processed_by' => User::factory()->counterAgent(),
        ];
    }

    public function excellent(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => VehicleCondition::Excellent,
        ]);
    }

    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => VehicleCondition::Damaged,
            'damage_notes' => fake()->sentence(7),
            'additional_charges' => fake()->randomElement([50000, 80000, 150000]),
        ]);
    }
}
