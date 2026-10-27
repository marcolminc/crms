<?php

namespace Database\Factories;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    private static int $plateSequence = 0;

    /**
     * Make/model pairs common in the Tanzanian market, with a matching body
     * type, seat count and a believable daily rate in the 60k-450k TZS band.
     *
     * @var array<int, array{make:string,model:string,type:VehicleType,seats:int,rate:int,fuel:string,trans:string}>
     */
    private const MODELS = [
        ['make' => 'Toyota', 'model' => 'Hilux', 'type' => VehicleType::Pickup, 'seats' => 5, 'rate' => 250000, 'fuel' => 'diesel', 'trans' => 'manual'],
        ['make' => 'Isuzu', 'model' => 'D-Max', 'type' => VehicleType::Pickup, 'seats' => 5, 'rate' => 240000, 'fuel' => 'diesel', 'trans' => 'manual'],
        ['make' => 'Nissan', 'model' => 'X-Trail', 'type' => VehicleType::Suv, 'seats' => 5, 'rate' => 200000, 'fuel' => 'petrol', 'trans' => 'automatic'],
        ['make' => 'Hyundai', 'model' => 'Tucson', 'type' => VehicleType::Suv, 'seats' => 5, 'rate' => 210000, 'fuel' => 'petrol', 'trans' => 'automatic'],
        ['make' => 'Toyota', 'model' => 'Passo', 'type' => VehicleType::Hatchback, 'seats' => 5, 'rate' => 90000, 'fuel' => 'petrol', 'trans' => 'automatic'],
        ['make' => 'Suzuki', 'model' => 'Swift', 'type' => VehicleType::Hatchback, 'seats' => 5, 'rate' => 80000, 'fuel' => 'petrol', 'trans' => 'manual'],
        ['make' => 'Toyota', 'model' => 'Hiace', 'type' => VehicleType::Minibus, 'seats' => 14, 'rate' => 180000, 'fuel' => 'diesel', 'trans' => 'manual'],
        ['make' => 'Toyota', 'model' => 'Coaster', 'type' => VehicleType::Coach, 'seats' => 30, 'rate' => 350000, 'fuel' => 'diesel', 'trans' => 'manual'],
        ['make' => 'Toyota', 'model' => 'Corolla', 'type' => VehicleType::Sedan, 'seats' => 5, 'rate' => 150000, 'fuel' => 'petrol', 'trans' => 'automatic'],
    ];

    private const FEATURE_POOL = ['AC', 'Bluetooth', 'USB', 'Reclining seats', 'Tinted glass', 'Alloy rims', 'Reverse camera', 'Roof rack', 'First aid kit'];

    public function definition(): array
    {
        $spec = self::MODELS[fake()->numberBetween(0, count(self::MODELS) - 1)];
        self::$plateSequence++;

        $features = fake()->randomElements(self::FEATURE_POOL, fake()->numberBetween(2, 5));

        return [
            'plate_number' => sprintf('T%03d %s', self::$plateSequence, strtoupper(fake()->lexify('???'))),
            'make' => $spec['make'],
            'model' => $spec['model'],
            'year' => fake()->numberBetween(2015, 2024),
            'type' => $spec['type'],
            'seats' => $spec['seats'],
            'transmission' => $spec['trans'],
            'fuel_type' => $spec['fuel'],
            'colour' => fake()->randomElement(['White', 'Silver', 'Grey', 'Black', 'Blue']),
            'mileage' => fake()->numberBetween(20000, 180000),
            'rate_per_day' => $spec['rate'],
            'status' => VehicleStatus::Available,
            'description' => fake()->optional(0.6)->sentence(8),
            'features' => array_values($features),
            'image_path' => null,
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VehicleStatus::Available,
        ]);
    }

    public function rented(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VehicleStatus::Rented,
        ]);
    }

    public function underMaintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VehicleStatus::Maintenance,
        ]);
    }

    public function outOfService(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VehicleStatus::OutOfService,
        ]);
    }

    /**
     * A premium vehicle at the top of the daily rate band.
     */
    public function expensive(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => VehicleType::Suv,
            'seats' => 7,
            'make' => 'Toyota',
            'model' => 'Land Cruiser Prado',
            'rate_per_day' => 450000,
        ]);
    }
}
