<?php

namespace Database\Factories;

use App\Enums\RentalStatus;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    protected $model = Rental::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', 'next week');
        $days = fake()->numberBetween(2, 10);
        $end = (clone $start)->modify("+{$days} days");

        return [
            'reservation_id' => null,
            'customer_id' => Customer::factory(),
            'vehicle_id' => Vehicle::factory(),
            'staff_id' => User::factory()->counterAgent(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            // days / subtotal / total_amount are derived in configure().
            'days' => $days + 1,
            'rate_per_day' => fake()->randomElement([90000, 150000, 200000, 250000]),
            'subtotal' => 0,
            'additional_charges' => 0,
            'discount' => 0,
            'total_amount' => 0,
            'status' => RentalStatus::Active,
            'pickup_location' => 'Mwanza City Office',
            'dropoff_location' => fake()->optional(0.6)->randomElement(['Mwanza City Office', 'Note: Mwanza Airport', 'Rock City Mall']),
        ];
    }

    /**
     * Keep the money columns internally consistent so a row can never be
     * written with a subtotal/total that disagrees with rate * days.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Rental $rental): void {
            $days = $rental->start_date->diffInDays($rental->end_date) + 1;
            $rental->days = $days;
            $rental->recalculate();
        });
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::Active,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::Overdue,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::Cancelled,
        ]);
    }

    /**
     * Bind the rental to an existing reservation and inherit the customer,
     * vehicle and period so the two rows always describe the same booking.
     */
    public function forReservation(Reservation $reservation): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_id' => $reservation->id,
            'customer_id' => $reservation->customer_id,
            'vehicle_id' => $reservation->vehicle_id,
            'start_date' => $reservation->start_date->format('Y-m-d'),
            'end_date' => $reservation->end_date->format('Y-m-d'),
            'rate_per_day' => (float) $reservation->vehicle->rate_per_day,
        ]);
    }
}
