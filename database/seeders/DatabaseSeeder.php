<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            UserRoleSeeder::class,
            PharmacySeeder::class,
            MedicineSeeder::class,
            InventorySeeder::class,
        ]);

        $owner1 = User::firstOrCreate(
            ['email' => 'owner1@example.com'],
            ['name' => 'Owner One', 'password' => bcrypt('password')]
        );

        $owner2 = User::firstOrCreate(
            ['email' => 'owner2@example.com'],
            ['name' => 'Owner Two', 'password' => bcrypt('password')]
        );

        $pharmacy1 = Pharmacy::create([
            'pharmacy_name' => 'Legazpi City Pharmacy',
            'pharmacyAddress' => '123 Rizal St., Legazpi City',
            'latitude' => 13.1475,
            'longitude' => 123.7431,
            'contactNumber' => '09171234567',
            'user_id' => $owner1->id,
            'status' => 'approved', // Add this
        ]);

        // Create Pharmacy 2
        $pharmacy2 = Pharmacy::create([
            'pharmacy_name' => 'Albay Medical Center Pharmacy',
            'pharmacyAddress' => '45 Quezon Ave., Legazpi City',
            'latitude' => 13.1490,
            'longitude' => 123.7455,
            'contactNumber' => '09179876543',
            'user_id' => $owner2->id,
            'status' => 'approved', // Add this
        ]);
    }
}