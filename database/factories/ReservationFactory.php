<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', 'next month');
        $days = fake()->numberBetween(2, 9);
        $end = (clone $start)->modify("+{$days} days");

        return [
            'customer_id' => Customer::factory(),
            'vehicle_id' => Vehicle::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'estimated_total' => 0,
            'status' => ReservationStatus::Pending,
            'notes' => null,
            'rejection_reason' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Reservation $reservation): void {
            $vehicle = $reservation->vehicle;
            $rate = $vehicle ? (float) $vehicle->rate_per_day : 0.0;
            $reservation->forceFill([
                'estimated_total' => round($rate * $reservation->days(), 2),
            ])->save();
        });
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Pending,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Confirmed,
            'confirmed_by' => User::factory()->rentalStaff(),
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Cancelled,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Rejected,
            'rejection_reason' => fake()->sentence(6),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Completed,
            'confirmed_by' => User::factory()->rentalStaff(),
            'confirmed_at' => now(),
        ]);
    }
}
