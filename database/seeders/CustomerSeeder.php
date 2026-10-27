<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Creation order fixes customer IDs 1..8; RentalHistorySeeder references
        // these same indexes. Each customer owns a login (password "password").
        $customers = [
            ['amina.hassan@example.co.tz', 'Amina Hassan', '0788 111 001', '12345601', 'DL-1200001', 'Mwanza', 'Buhongwa Street, Mwanza', 'Zawedi Hassan', '0712 222 001'],
            ['joseph.mushi@example.co.tz', 'Joseph Mushi', '0788 111 002', '12345602', 'DL-1200002', 'Mwanza', 'Nyerere Road, Mwanza', 'Anna Mushi', '0712 222 002'],
            ['fatuma.salum@example.co.tz', 'Fatuma Salum', '0788 111 003', '12345603', 'DL-1200003', 'Mwanza', 'Nyakashi, Mwanza', 'Salum Ally', '0712 222 003'],
            ['emmanuel.charles@example.co.tz', 'Emmanuel Charles', '0788 111 004', '12345604', 'DL-1200004', 'Dodoma', 'Vittland, Dodoma', 'Esther Charles', '0712 222 004'],
            ['neema.john@example.co.tz', 'Neema John', '0788 111 005', '12345605', 'DL-1200005', 'Mwanza', 'Pasiansi, Mwanza', 'John Peter', '0712 222 005'],
            ['baraka.ngowi@example.co.tz', 'Baraka Ngowi', '0788 111 006', '12345606', 'DL-1200006', 'Arusha', 'Leidola Road, Arusha', 'Sarah Ngowi', '0712 222 006'],
            ['zainab.mohamed@example.co.tz', 'Zainab Mohamed', '0788 111 007', '12345607', 'DL-1200007', 'Mwanza', 'Sokoni area, Mwanza', 'Mohamed Khamis', '0712 222 007'],
            ['peter.massawe@example.co.tz', 'Peter Massawe', '0788 111 008', '12345608', 'DL-1200008', 'Shinyanga', 'Bombangula, Shinyanga', 'Ruth Massawe', '0712 222 008'],
        ];

        foreach ($customers as $c) {
            [$email, $name, $phone, $nid, $dl, $city, $address, $emgName, $emgPhone] = $c;

            Customer::factory()->create([
                'user_id' => User::factory()->customer()->create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                ])->id,
                'national_id' => $nid,
                'driving_license_no' => $dl,
                'date_of_birth' => '1990-0'.$this->monthFor($email).'-15',
                'city' => $city,
                'address' => $address,
                'emergency_contact_name' => $emgName,
                'emergency_contact_phone' => $emgPhone,
            ]);
        }
    }

    private function monthFor(string $email): int
    {
        return (crc32($email) % 8) + 1;
    }
}
