<?php

namespace Database\Seeders;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        // Deterministic fleet for Land Valley's Travels. Creation order fixes
        // vehicle IDs 1..18; RentalHistorySeeder references these same plates.
        // The status column is chosen to agree with the open rentals, live
        // reservations and ongoing maintenance rows the history seeder creates.
        $fleet = [
            ['T234 ABC', 'Toyota', 'Hilux', 2021, VehicleType::Pickup, 5, 'manual', 'diesel', 'White', 84000, 250000, VehicleStatus::Rented, ['AC', 'Roll bar', 'Tool kit']],
            ['T 456 XY', 'Isuzu', 'D-Max', 2020, VehicleType::Pickup, 5, 'manual', 'diesel', 'Silver', 96000, 240000, VehicleStatus::Available, ['AC', 'Roll bar']],
            ['T112 PQR', 'Nissan', 'X-Trail', 2019, VehicleType::Suv, 5, 'automatic', 'petrol', 'Grey', 120000, 200000, VehicleStatus::Available, ['AC', 'Alloy rims']],
            ['T225 STU', 'Toyota', 'Passo', 2022, VehicleType::Hatchback, 5, 'automatic', 'petrol', 'Blue', 41000, 90000, VehicleStatus::Available, ['AC', 'Bluetooth']],
            ['T330 VWX', 'Suzuki', 'Swift', 2021, VehicleType::Hatchback, 5, 'manual', 'petrol', 'Red', 55000, 80000, VehicleStatus::Available, ['AC', 'USB']],
            ['T410 YZA', 'Toyota', 'Hiace', 2018, VehicleType::Minibus, 14, 'manual', 'diesel', 'White', 160000, 180000, VehicleStatus::Rented, ['AC', 'Reclining seats', 'Public address']],
            ['T515 BCD', 'Toyota', 'Coaster', 2017, VehicleType::Coach, 30, 'manual', 'diesel', 'Cream', 210000, 350000, VehicleStatus::Rented, ['AC', 'Curtains', 'First aid kit']],
            ['T620 EFG', 'Hyundai', 'Tucson', 2020, VehicleType::Suv, 5, 'automatic', 'petrol', 'Black', 78000, 210000, VehicleStatus::Maintenance, ['AC', 'Reverse camera', 'Alloy rims']],
            ['T735 HIJ', 'Toyota', 'Corolla', 2021, VehicleType::Sedan, 5, 'automatic', 'petrol', 'Silver', 62000, 150000, VehicleStatus::Available, ['AC', 'Bluetooth']],
            ['T840 KLM', 'Toyota', 'Land Cruiser Prado', 2019, VehicleType::Suv, 7, 'automatic', 'diesel', 'Grey', 130000, 450000, VehicleStatus::Reserved, ['AC', 'Roof rack', 'Leather seats', '4WD']],
            ['T955 NOP', 'Toyota', 'Fortuner', 2020, VehicleType::Suv, 7, 'automatic', 'diesel', 'White', 98000, 320000, VehicleStatus::Reserved, ['AC', 'Alloy rims', '4WD']],
            ['T060 QRS', 'Nissan', 'Navara', 2019, VehicleType::Pickup, 5, 'manual', 'diesel', 'Blue', 110000, 230000, VehicleStatus::Available, ['AC', 'Roll bar']],
            ['T175 TUV', 'Toyota', 'Hiace', 2016, VehicleType::Minibus, 15, 'manual', 'diesel', 'White', 190000, 190000, VehicleStatus::Maintenance, ['AC', 'Reclining seats']],
            ['T290 WXY', 'Mitsubishi', 'L200', 2021, VehicleType::Pickup, 5, 'manual', 'diesel', 'Silver', 72000, 220000, VehicleStatus::Available, ['AC', 'Tool kit']],
            ['T305 YZA', 'Toyota', 'Coaster', 2018, VehicleType::Coach, 33, 'manual', 'diesel', 'White', 175000, 360000, VehicleStatus::Available, ['AC', 'Curtains', 'Public address']],
            ['T420 BCD', 'Hyundai', 'H1', 2015, VehicleType::Minibus, 12, 'automatic', 'diesel', 'Grey', 205000, 170000, VehicleStatus::OutOfService, ['AC', 'Reclining seats']],
            ['T530 EFG', 'Toyota', 'Hilux', 2022, VehicleType::Pickup, 5, 'manual', 'diesel', 'Black', 48000, 260000, VehicleStatus::Rented, ['AC', 'Roll bar', 'Reverse camera']],
            ['T640 HIJ', 'Toyota', 'Allion', 2020, VehicleType::Sedan, 5, 'automatic', 'petrol', 'White', 88000, 130000, VehicleStatus::Available, ['AC', 'USB']],
        ];

        foreach ($fleet as [$plate, $make, $model, $year, $type, $seats, $trans, $fuel, $colour, $mileage, $rate, $status, $features]) {
            Vehicle::factory()->create([
                'plate_number' => $plate,
                'make' => $make,
                'model' => $model,
                'year' => $year,
                'type' => $type,
                'seats' => $seats,
                'transmission' => $trans,
                'fuel_type' => $fuel,
                'colour' => $colour,
                'mileage' => $mileage,
                'rate_per_day' => $rate,
                'status' => $status,
                'features' => $features,
                'description' => null,
            ]);
        }
    }
}
