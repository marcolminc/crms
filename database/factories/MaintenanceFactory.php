<?php

namespace Database\Factories;

use App\Enums\MaintenanceStatus;
use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Maintenance>
 */
class MaintenanceFactory extends Factory
{
    protected $model = Maintenance::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'title' => fake()->randomElement([
                'Full service', 'Brake replacement', 'Oil change', 'Tyre replacement',
                'Clutch repair', 'AC recharge', 'Battery replacement', 'Suspension work',
            ]),
            'description' => fake()->optional(0.6)->sentence(8),
            'service_provider' => fake()->randomElement(['TBC Garage Mwanza', 'Kariakuli Motors', 'Bongwe Auto Clinic', 'Mwanza Tyre Centre']),
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'cost' => null,
            'status' => MaintenanceStatus::Scheduled,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceStatus::Scheduled,
            'end_date' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceStatus::InProgress,
            'end_date' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceStatus::Completed,
            'end_date' => now()->toDateString(),
            'cost' => fake()->randomElement([90000, 120000, 250000, 300000]),
        ]);
    }
}
