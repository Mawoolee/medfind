<?php

namespace Database\Seeders;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\Concerns\SeedsBatchInventory;
use Illuminate\Database\Seeder;

class InventoryTestSeeder extends Seeder
{
    use SeedsBatchInventory;

    public function run()
    {
        // Ensure a user exists
        $user = User::first();
        if (! $user) {
            $user = User::create([
                'name' => 'Test Pharmacy User',
                'email' => 'pharmacy@example.test',
                'password' => bcrypt('password'),
                'role' => 'pharmacy',
            ]);
            $this->command->info('Created test user: '.$user->email);
        }

        // Ensure a pharmacy exists for the user
        $pharmacy = Pharmacy::where('user_id', $user->id)->first();
        if (! $pharmacy) {
            $pharmacy = Pharmacy::create([
                'pharmacy_name' => 'Test Pharmacy',
                'pharmacyAddress' => '123 Test St',
                'latitude' => 14.5995,
                'longitude' => 120.9842,
                'contactNumber' => '09171234567',
                'user_id' => $user->id,
                'status' => 'approved',
            ]);
            $this->command->info('Created pharmacy: '.$pharmacy->pharmacy_name);
        }

        $sample = [
            ['name' => 'Paracetamol', 'dosage' => '500mg', 'manufacturer' => 'Acme Pharma'],
            ['name' => 'Amoxicillin', 'dosage' => '250mg', 'manufacturer' => 'HealthCorp'],
            ['name' => 'Cetirizine', 'dosage' => '10mg', 'manufacturer' => 'AllergyMed'],
            ['name' => 'Metformin', 'dosage' => '500mg', 'manufacturer' => 'GlucoPharm'],
            ['name' => 'Atorvastatin', 'dosage' => '20mg', 'manufacturer' => 'CardioLab'],
            ['name' => 'Omeprazole', 'dosage' => '20mg', 'manufacturer' => 'GastroSafe'],
            ['name' => 'Loratadine', 'dosage' => '10mg', 'manufacturer' => 'AllergyMed'],
            ['name' => 'Ibuprofen', 'dosage' => '200mg', 'manufacturer' => 'PainAway'],
            ['name' => 'Insulin (Vial)', 'dosage' => '100IU/ml', 'manufacturer' => 'BioCare'],
            ['name' => 'Vaccine X', 'dosage' => '0.5ml', 'manufacturer' => 'VaxCorp'],
            ['name' => 'Cough Syrup', 'dosage' => '100ml', 'manufacturer' => 'SyrupMakers'],
            ['name' => 'Azithromycin', 'dosage' => '250mg', 'manufacturer' => 'AntibioticsCo'],
            ['name' => 'Prednisone', 'dosage' => '5mg', 'manufacturer' => 'SteroidLabs'],
            ['name' => 'Vitamin C', 'dosage' => '500mg', 'manufacturer' => 'NutriPlus'],
            ['name' => 'Calcium Carbonate', 'dosage' => '600mg', 'manufacturer' => 'BoneHealth'],
        ];

        $count = 0;
        foreach ($sample as $s) {
            $medicine = Medicine::firstOrCreate([
                'medicine_name' => $s['name'],
            ], [
                'dosage' => $s['dosage'],
                'manufacturer' => $s['manufacturer'],
                'requiresPrescription' => in_array($s['name'], ['Amoxicillin', 'Azithromycin', 'Prednisone', 'Insulin (Vial)']),
                'category' => 'General',
            ]);

            $quantity = rand(0, 120);
            $price = rand(50, 1500) / 10.0;
            $this->seedBatchInventory($pharmacy, $medicine, $quantity, $price, [
                'batch_number' => 'TEST-'.$pharmacy->id.'-'.$medicine->id,
                'cold_chain' => in_array($s['name'], ['Insulin (Vial)', 'Vaccine X'], true),
                'received_reference' => 'inventory-test-seeder',
            ]);

            $count++;
        }

        $this->command->info("Inserted/updated {$count} inventory items for pharmacy: {$pharmacy->pharmacy_name}");
    }
}
