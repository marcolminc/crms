<?php

namespace Database\Factories;

use App\Enums\FineStatus;
use App\Models\Rental;
use App\Models\TrafficFine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrafficFine>
 */
class TrafficFineFactory extends Factory
{
    protected $model = TrafficFine::class;

    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'vehicle_id' => null,
            'reference' => 'TRC'.fake()->unique()->numerify('######'),
            'description' => fake()->randomElement(['Speeding', 'Running a red light', 'Illegal parking', 'No seatbelt']),
            'amount' => fake()->randomElement([30000, 50000, 120000, 200000]),
            'issued_at' => now()->toDateString(),
            'liability' => null,
            'status' => FineStatus::Pending,
            'recorded_by' => User::factory()->counterAgent(),
            'decided_by' => null,
            'decided_at' => null,
            'notes' => null,
        ];
    }
}
