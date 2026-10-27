<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    /**
     * Deterministic staff logins for the demo. Every account uses the password
     * "password" (hashed through the User model's `hashed` cast).
     */
    public function run(): void
    {
        $staff = [
            ['admin@landvalley.co.tz', 'Neema Shirima', UserRole::Admin, '0788 000 001'],
            ['fleet@landvalley.co.tz', 'Daudi Mkwawa', UserRole::FleetManager, '0788 000 002'],
            ['staff@landvalley.co.tz', 'Rehema Bakari', UserRole::RentalStaff, '0788 000 003'],
            ['accounts@landvalley.co.tz', 'Simon Mbilila', UserRole::Accountant, '0788 000 004'],
            ['manager@landvalley.co.tz', 'Grace Ulimwengu', UserRole::Manager, '0788 000 005'],
        ];

        foreach ($staff as [$email, $name, $role, $phone]) {
            User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'role' => $role,
                'phone' => $phone,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }
    }
}
