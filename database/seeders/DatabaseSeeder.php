<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Maintenance;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReturn;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Orchestrates the demo fleet for "Land Valley's Travels" (Mwanza).
     * Order matters: users first, then the fleet, then customer logins, and
     * finally the booking/rental history that references all of them.
     */
    public function run(): void
    {
        $this->call([
            UserRoleSeeder::class,
            VehicleSeeder::class,
            CustomerSeeder::class,
            RentalHistorySeeder::class,
        ]);

        $this->command->info('Land Valley\'s Travels demo data seeded.');
        $this->command->info('All demo accounts (staff and customers) use the password: password');

        $this->command->table('Seeded rows', [
            ['Users', User::count()],
            ['Customers', Customer::count()],
            ['Vehicles', Vehicle::count()],
            ['Reservations', Reservation::count()],
            ['Rentals', Rental::count()],
            ['Payments', Payment::count()],
            ['Vehicle returns', VehicleReturn::count()],
            ['Maintenance jobs', Maintenance::count()],
            ['Notifications', Notification::count()],
        ]);
    }
}
