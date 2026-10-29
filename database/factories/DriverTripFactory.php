<?php

namespace Database\Factories;

use App\Models\DriverTrip;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverTrip>
 */
class DriverTripFactory extends Factory
{
    protected $model = DriverTrip::class;

    public function definition(): array
    {
        $start = fake()->numberBetween(10000, 60000);

        return [
            'rental_id' => Rental::factory(),
            'driver_id' => User::factory()->driver(),
            'trip_date' => fake()->dateTimeBetween('-1 week', 'now')->format('Y-m-d'),
            'started_at' => '07:'.fake()->numberBetween(10, 59).':00',
            'ended_at' => '17:'.fake()->numberBetween(10, 59).':00',
            'odometer_start' => $start,
            'odometer_end' => $start + fake()->numberBetween(20, 260),
            'incident_notes' => fake()->optional(0.3)->sentence(6),
        ];
    }
}
