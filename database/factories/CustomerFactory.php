<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    private static $cities = [
        'Mwanza', 'Mwanza', 'Mwanza', 'Mwanza', 'Dodoma', 'Arusha', 'Dar es Salaam', 'Shinyanga', 'Tabora', 'Mbeya', 'Geita',
    ];

    public function definition(): array
    {
        return [
            // A Customer always owns a login; default to a customer-role user.
            'user_id' => User::factory()->customer(),
            'national_id' => fake()->unique()->numerify('########'),
            'passport_no' => fake()->optional(0.3)->regexify('[A-Z][0-9]{7}'),
            'driving_license_no' => 'DL-'.fake()->numerify('#######'),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'city' => fake()->randomElement(self::$cities),
            'address' => fake()->streetAddress(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '06'.fake()->numerify('########'),
            'notes' => null,
        ];
    }

    public function inMwanza(): static
    {
        return $this->state(fn (array $attributes) => [
            'city' => 'Mwanza',
        ]);
    }
}
