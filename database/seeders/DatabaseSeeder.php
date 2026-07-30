<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Pharmacy;
use App\Models\Medicine;
use App\Models\InventoryItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        echo "\n============================================\n";
        echo "🚀 STARTING MEDFIND DATABASE SEEDER\n";
        echo "============================================\n\n";

        // ============================================
        // 1. CREATE ADMIN
        // ============================================
        $admin = User::updateOrCreate(
            ['email' => 'admin@medfind.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
        echo "✅ Admin user: " . $admin->email . "\n";

        // ============================================
        // 2. CREATE PHARMACY OWNER 1
        // ============================================
        $owner1 = User::updateOrCreate(
            ['email' => 'pharmacy1@medfind.com'],
            [
                'name' => 'Pharmacy Owner 1',
                'password' => Hash::make('password'),
                'role' => 'pharmacy',
            ]
        );
        echo "✅ Pharmacy Owner 1: " . $owner1->email . "\n";

        // ============================================
        // 3. CREATE PHARMACY OWNER 2
        // ============================================
        $owner2 = User::updateOrCreate(
            ['email' => 'pharmacy2@medfind.com'],
            [
                'name' => 'Pharmacy Owner 2',
                'password' => Hash::make('password'),
                'role' => 'pharmacy',
            ]
        );
        echo "✅ Pharmacy Owner 2: " . $owner2->email . "\n";

        // ============================================
        // 4. CREATE PHARMACY 1
        // ============================================
        $pharmacy1 = Pharmacy::updateOrCreate(
            ['pharmacy_name' => 'Legazpi City Pharmacy'],
            [
                'pharmacyAddress' => '123 Rizal St., Legazpi City',
                'latitude' => 13.1475,
                'longitude' => 123.7431,
                'contactNumber' => '09171234567',
                'user_id' => $owner1->id,
                'status' => 'approved',
            ]
        );
        echo "✅ Pharmacy 1: " . $pharmacy1->pharmacy_name . "\n";

        // ============================================
        // 5. CREATE PHARMACY 2
        // ============================================
        $pharmacy2 = Pharmacy::updateOrCreate(
            ['pharmacy_name' => 'Albay Medical Center Pharmacy'],
            [
                'pharmacyAddress' => '45 Quezon Ave., Legazpi City',
                'latitude' => 13.1490,
                'longitude' => 123.7455,
                'contactNumber' => '09179876543',
                'user_id' => $owner2->id,
                'status' => 'approved',
            ]
        );
        echo "✅ Pharmacy 2: " . $pharmacy2->pharmacy_name . "\n";

        // ============================================
        // 6. CREATE ADDITIONAL PHARMACIES (for map display)
        // ============================================
        
        $pharmacy3 = Pharmacy::updateOrCreate(
            ['pharmacy_name' => 'Mercury Drug - Legazpi'],
            [
                'pharmacyAddress' => 'Rizal St., Legazpi City',
                'latitude' => 13.1486,
                'longitude' => 123.7412,
                'contactNumber' => '09171234568',
                'user_id' => null,
                'status' => 'approved',
            ]
        );
        echo "✅ Pharmacy 3: " . $pharmacy3->pharmacy_name . "\n";

        $pharmacy4 = Pharmacy::updateOrCreate(
            ['pharmacy_name' => 'Bicol Ultra Drug'],
            [
                'pharmacyAddress' => '4PQM+H57, Rizal St, Legazpi City',
                'latitude' => 13.13905,
                'longitude' => 123.73290,
                'contactNumber' => '09171234569',
                'user_id' => null,
                'status' => 'approved',
            ]
        );
        echo "✅ Pharmacy 4: " . $pharmacy4->pharmacy_name . "\n";

        $pharmacy5 = Pharmacy::updateOrCreate(
            ['pharmacy_name' => 'South Star Drug'],
            [
                'pharmacyAddress' => 'Lapu-Lapu St., Legazpi City',
                'latitude' => 13.1550,
                'longitude' => 123.7395,
                'contactNumber' => '09171234570',
                'user_id' => null,
                'status' => 'approved',
            ]
        );
        echo "✅ Pharmacy 5: " . $pharmacy5->pharmacy_name . "\n";

        $pharmacy6 = Pharmacy::updateOrCreate(
            ['pharmacy_name' => 'Generics Pharmacy'],
            [
                'pharmacyAddress' => 'Peñaranda St., Legazpi City',
                'latitude' => 13.1420,
                'longitude' => 123.7300,
                'contactNumber' => '09171234571',
                'user_id' => null,
                'status' => 'approved',
            ]
        );
        echo "✅ Pharmacy 6: " . $pharmacy6->pharmacy_name . "\n";

        $pharmacy7 = Pharmacy::updateOrCreate(
            ['pharmacy_name' => 'ACE Medical Pharmacy'],
            [
                'pharmacyAddress' => 'ACE Medical Center, Legazpi',
                'latitude' => 13.1402,
                'longitude' => 123.7350,
                'contactNumber' => '09171234572',
                'user_id' => null,
                'status' => 'approved',
            ]
        );
        echo "✅ Pharmacy 7: " . $pharmacy7->pharmacy_name . "\n";

        // ============================================
        // 7. UPDATE pharmacy_id for pharmacy users
        // ============================================
        $owner1->pharmacy_id = $pharmacy1->id;
        $owner1->save();
        $owner2->pharmacy_id = $pharmacy2->id;
        $owner2->save();
        echo "✅ Updated pharmacy_id for owners\n";

        // ============================================
        // 8. CREATE MEDICINES
        // ============================================
        $medicinesData = [
            ['medicine_name' => 'Paracetamol 500mg', 'dosage' => '500mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Analgesic'],
            ['medicine_name' => 'Amoxicillin 500mg', 'dosage' => '500mg', 'manufacturer' => 'Pharmaserv', 'requiresPrescription' => true, 'category' => 'Antibiotic'],
            ['medicine_name' => 'Ibuprofen 200mg', 'dosage' => '200mg', 'manufacturer' => 'Pfizer', 'requiresPrescription' => false, 'category' => 'NSAID'],
            ['medicine_name' => 'Cetirizine 10mg', 'dosage' => '10mg', 'manufacturer' => 'GlaxoSmithKline', 'requiresPrescription' => false, 'category' => 'Antihistamine'],
            ['medicine_name' => 'Loperamide 2mg', 'dosage' => '2mg', 'manufacturer' => 'Johnson & Johnson', 'requiresPrescription' => false, 'category' => 'Antidiarrheal'],
            ['medicine_name' => 'Mefenamic Acid 500mg', 'dosage' => '500mg', 'manufacturer' => 'Pfizer', 'requiresPrescription' => false, 'category' => 'NSAID'],
            ['medicine_name' => 'Losartan 50mg', 'dosage' => '50mg', 'manufacturer' => 'Novartis', 'requiresPrescription' => true, 'category' => 'Antihypertensive'],
            ['medicine_name' => 'Metformin 500mg', 'dosage' => '500mg', 'manufacturer' => 'Merck', 'requiresPrescription' => true, 'category' => 'Antidiabetic'],
            ['medicine_name' => 'Omeprazole 20mg', 'dosage' => '20mg', 'manufacturer' => 'AstraZeneca', 'requiresPrescription' => true, 'category' => 'Antacid'],
            ['medicine_name' => 'Ascorbic Acid 500mg', 'dosage' => '500mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Vitamin'],
            ['medicine_name' => 'Hyoscine 10mg', 'dosage' => '10mg', 'manufacturer' => 'Boehringer Ingelheim', 'requiresPrescription' => false, 'category' => 'Antispasmodic'],
        ];

        $createdMedicines = [];
        foreach ($medicinesData as $medData) {
            $medicine = Medicine::updateOrCreate(
                ['medicine_name' => $medData['medicine_name'], 'dosage' => $medData['dosage']],
                $medData
            );
            $createdMedicines[] = $medicine;
            echo "✅ Medicine: " . $medicine->medicine_name . "\n";
        }

        // ============================================
        // 9. CREATE INVENTORY ITEMS
        // ============================================
        $pharmacies = Pharmacy::all();
        $medicines = Medicine::all();

        $inventoryCount = 0;
        $inventoryData = [
            // Pharmacy 1 (Legazpi City Pharmacy)
            ['pharmacy_id' => $pharmacy1->id, 'medicine_id' => 1, 'stock' => 45, 'price' => 85.00],
            ['pharmacy_id' => $pharmacy1->id, 'medicine_id' => 2, 'stock' => 23, 'price' => 125.00],
            ['pharmacy_id' => $pharmacy1->id, 'medicine_id' => 3, 'stock' => 12, 'price' => 95.00],
            ['pharmacy_id' => $pharmacy1->id, 'medicine_id' => 6, 'stock' => 8, 'price' => 110.00],
            
            // Pharmacy 2 (Albay Medical Center)
            ['pharmacy_id' => $pharmacy2->id, 'medicine_id' => 1, 'stock' => 28, 'price' => 92.00],
            ['pharmacy_id' => $pharmacy2->id, 'medicine_id' => 2, 'stock' => 8, 'price' => 135.00],
            ['pharmacy_id' => $pharmacy2->id, 'medicine_id' => 4, 'stock' => 15, 'price' => 60.00],
            ['pharmacy_id' => $pharmacy2->id, 'medicine_id' => 5, 'stock' => 20, 'price' => 48.00],
            
            // Pharmacy 3 (Mercury Drug)
            ['pharmacy_id' => $pharmacy3->id, 'medicine_id' => 1, 'stock' => 50, 'price' => 88.00],
            ['pharmacy_id' => $pharmacy3->id, 'medicine_id' => 2, 'stock' => 30, 'price' => 130.00],
            ['pharmacy_id' => $pharmacy3->id, 'medicine_id' => 3, 'stock' => 25, 'price' => 98.00],
            ['pharmacy_id' => $pharmacy3->id, 'medicine_id' => 4, 'stock' => 10, 'price' => 65.00],
            
            // Pharmacy 4 (Bicol Ultra Drug)
            ['pharmacy_id' => $pharmacy4->id, 'medicine_id' => 1, 'stock' => 35, 'price' => 90.00],
            ['pharmacy_id' => $pharmacy4->id, 'medicine_id' => 2, 'stock' => 15, 'price' => 140.00],
            ['pharmacy_id' => $pharmacy4->id, 'medicine_id' => 3, 'stock' => 20, 'price' => 100.00],
            ['pharmacy_id' => $pharmacy4->id, 'medicine_id' => 5, 'stock' => 12, 'price' => 50.00],
            
            // Pharmacy 5 (South Star Drug)
            ['pharmacy_id' => $pharmacy5->id, 'medicine_id' => 1, 'stock' => 40, 'price' => 82.00],
            ['pharmacy_id' => $pharmacy5->id, 'medicine_id' => 2, 'stock' => 5, 'price' => 120.00],
            ['pharmacy_id' => $pharmacy5->id, 'medicine_id' => 3, 'stock' => 18, 'price' => 92.00],
            ['pharmacy_id' => $pharmacy5->id, 'medicine_id' => 6, 'stock' => 10, 'price' => 105.00],
            
            // Pharmacy 6 (Generics Pharmacy)
            ['pharmacy_id' => $pharmacy6->id, 'medicine_id' => 1, 'stock' => 25, 'price' => 75.00],
            ['pharmacy_id' => $pharmacy6->id, 'medicine_id' => 2, 'stock' => 0, 'price' => 110.00],
            ['pharmacy_id' => $pharmacy6->id, 'medicine_id' => 5, 'stock' => 15, 'price' => 45.00],
            
            // Pharmacy 7 (ACE Medical Pharmacy)
            ['pharmacy_id' => $pharmacy7->id, 'medicine_id' => 1, 'stock' => 30, 'price' => 95.00],
            ['pharmacy_id' => $pharmacy7->id, 'medicine_id' => 2, 'stock' => 0, 'price' => 145.00],
            ['pharmacy_id' => $pharmacy7->id, 'medicine_id' => 3, 'stock' => 8, 'price' => 102.00],
        ];

        foreach ($inventoryData as $data) {
            InventoryItem::updateOrCreate(
                [
                    'pharmacy_id' => $data['pharmacy_id'],
                    'medicine_id' => $data['medicine_id'],
                ],
                [
                    'stockQuantity' => $data['stock'],
                    'price' => $data['price'],
                    'status' => $data['stock'] > 0 ? 'available' : 'out_of_stock',
                ]
            );
            $inventoryCount++;
        }
        echo "✅ Created $inventoryCount inventory items\n";

        // ============================================
        // 10. CREATE CONSUMER USER
        // ============================================
        $consumer = User::updateOrCreate(
            ['email' => 'consumer@medfind.com'],
            [
                'name' => 'John Consumer',
                'password' => Hash::make('password'),
                'role' => 'consumer',
            ]
        );
        echo "✅ Consumer user: " . $consumer->email . "\n";

        // ============================================
        // SUMMARY
        // ============================================
        echo "\n============================================\n";
        echo "✅ SEEDING COMPLETED SUCCESSFULLY!\n";
        echo "============================================\n";
        echo "📊 Summary:\n";
        echo "   - Users: " . User::count() . "\n";
        echo "   - Pharmacies: " . Pharmacy::count() . "\n";
        echo "   - Medicines: " . Medicine::count() . "\n";
        echo "   - Inventory Items: " . InventoryItem::count() . "\n";
        echo "============================================\n";
        echo "\n🔑 LOGIN CREDENTIALS:\n";
        echo "   👤 Admin:     admin@medfind.com / password\n";
        echo "   🏪 Pharmacy:  pharmacy1@medfind.com / password\n";
        echo "   🏪 Pharmacy:  pharmacy2@medfind.com / password\n";
        echo "   👤 Consumer:  consumer@medfind.com / password\n";
        echo "============================================\n\n";
    }
}