<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        echo "\n============================================\n";
        echo "  MEDFIND DEMO SEEDER - Thesis Defense\n";
        echo "============================================\n\n";

        // Disable foreign key checks for truncation
        Schema::disableForeignKeyConstraints();
        DB::table('inventory_items')->truncate();
        DB::table('messages')->truncate();
        DB::table('medicines')->truncate();
        DB::table('pharmacies')->truncate();
        DB::table('users')->truncate();
        Schema::enableForeignKeyConstraints();

        echo "[1/5] Cleared existing data.\n";

        // ============================================
        // ADMIN USER
        // ============================================
        $admin = User::create([
            'name'     => 'System Admin',
            'email'    => 'admin@medfind.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);
        echo "[2/5] Admin user created: admin@medfind.com\n";

        // ============================================
        // CONSUMER USERS
        // ============================================
        $consumer1 = User::create([
            'name'     => 'Juan Dela Cruz',
            'email'    => 'consumer1@demo.com',
            'password' => Hash::make('password'),
            'role'     => 'consumer',
        ]);

        $consumer2 = User::create([
            'name'     => 'Maria Santos',
            'email'    => 'consumer2@demo.com',
            'password' => Hash::make('password'),
            'role'     => 'consumer',
        ]);
        echo "       Consumer users created: consumer1@demo.com, consumer2@demo.com\n";

        // ============================================
        // PHARMACY DATA
        // ============================================
        $pharmaciesData = [
            [
                'name'    => 'Mercury Drug Legazpi',
                'email'   => 'mercury@demo.com',
                'owner'   => 'Mercury Drug Owner',
                'address' => 'Rizal St, Legazpi City, Albay',
                'lat'     => 13.1391,
                'lng'     => 123.7338,
                'contact' => '0917-123-4567',
                'hours'   => 'Mon-Sat 8:00 AM - 9:00 PM',
            ],
            [
                'name'    => 'Rose Pharmacy Legazpi',
                'email'   => 'rose@demo.com',
                'owner'   => 'Rose Pharmacy Owner',
                'address' => 'Penaranda St, Legazpi City, Albay',
                'lat'     => 13.1405,
                'lng'     => 123.7350,
                'contact' => '0918-234-5678',
                'hours'   => 'Mon-Sat 8:30 AM - 8:30 PM',
            ],
            [
                'name'    => 'Generika Drugstore Legazpi',
                'email'   => 'generika@demo.com',
                'owner'   => 'Generika Drugstore Owner',
                'address' => 'Quezon Ave, Legazpi City, Albay',
                'lat'     => 13.1420,
                'lng'     => 123.7325,
                'contact' => '0919-345-6789',
                'hours'   => 'Mon-Sun 7:30 AM - 9:00 PM',
            ],
            [
                'name'    => 'South Star Drug',
                'email'   => 'southstar@demo.com',
                'owner'   => 'South Star Drug Owner',
                'address' => 'Imperial St, Legazpi City, Albay',
                'lat'     => 13.1380,
                'lng'     => 123.7360,
                'contact' => '0920-456-7890',
                'hours'   => 'Mon-Sat 8:00 AM - 10:00 PM',
            ],
            [
                'name'    => 'TGP Pharmacy Daraga',
                'email'   => 'tgp@demo.com',
                'owner'   => 'TGP Pharmacy Owner',
                'address' => 'Daraga, Albay',
                'lat'     => 13.1590,
                'lng'     => 123.7120,
                'contact' => '0921-567-8901',
                'hours'   => 'Mon-Sat 7:00 AM - 8:00 PM',
            ],
        ];

        $pharmacies = [];
        foreach ($pharmaciesData as $pData) {
            // Create pharmacy user account
            $user = User::create([
                'name'     => $pData['owner'],
                'email'    => $pData['email'],
                'password' => Hash::make('password'),
                'role'     => 'pharmacy',
            ]);

            // Create pharmacy
            $pharmacy = Pharmacy::create([
                'pharmacy_name'   => $pData['name'],
                'pharmacyAddress' => $pData['address'],
                'latitude'        => $pData['lat'],
                'longitude'       => $pData['lng'],
                'contactNumber'   => $pData['contact'],
                'operating_hours' => $pData['hours'],
                'status'          => 'approved',
                'user_id'         => $user->id,
            ]);

            // Link user to pharmacy
            $user->pharmacy_id = $pharmacy->id;
            $user->save();

            $pharmacies[] = $pharmacy;
        }
        echo "[3/5] 5 Pharmacies created with owner accounts.\n";

        // ============================================
        // MEDICINES (20 common Philippine medicines)
        // ============================================
        $medicinesData = [
            ['medicine_name' => 'Biogesic (Paracetamol)', 'dosage' => '500mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Pain Relief'],
            ['medicine_name' => 'Neozep (Phenylephrine + Chlorphenamine)', 'dosage' => '10mg/2mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Cold & Flu'],
            ['medicine_name' => 'Bioflu (Phenylpropanolamine + Chlorphenamine + Paracetamol)', 'dosage' => '25mg/2mg/500mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Cold & Flu'],
            ['medicine_name' => 'Solmux (Carbocisteine)', 'dosage' => '500mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Respiratory'],
            ['medicine_name' => 'Mefenamic Acid', 'dosage' => '500mg', 'manufacturer' => 'Generic', 'requiresPrescription' => false, 'category' => 'Pain Relief'],
            ['medicine_name' => 'Amoxicillin', 'dosage' => '500mg', 'manufacturer' => 'Generic', 'requiresPrescription' => true, 'category' => 'Antibiotics'],
            ['medicine_name' => 'Losartan', 'dosage' => '50mg', 'manufacturer' => 'Generic', 'requiresPrescription' => true, 'category' => 'Cardiovascular'],
            ['medicine_name' => 'Metformin', 'dosage' => '500mg', 'manufacturer' => 'Generic', 'requiresPrescription' => true, 'category' => 'Cardiovascular'],
            ['medicine_name' => 'Amlodipine', 'dosage' => '5mg', 'manufacturer' => 'Generic', 'requiresPrescription' => true, 'category' => 'Cardiovascular'],
            ['medicine_name' => 'Cetirizine', 'dosage' => '10mg', 'manufacturer' => 'Generic', 'requiresPrescription' => false, 'category' => 'Cold & Flu'],
            ['medicine_name' => 'Loperamide', 'dosage' => '2mg', 'manufacturer' => 'Generic', 'requiresPrescription' => false, 'category' => 'Digestive'],
            ['medicine_name' => 'Omeprazole', 'dosage' => '20mg', 'manufacturer' => 'Generic', 'requiresPrescription' => false, 'category' => 'Digestive'],
            ['medicine_name' => 'Ascorbic Acid (Vitamin C)', 'dosage' => '500mg', 'manufacturer' => 'Generic', 'requiresPrescription' => false, 'category' => 'Vitamins'],
            ['medicine_name' => 'Salbutamol', 'dosage' => '2mg', 'manufacturer' => 'Generic', 'requiresPrescription' => true, 'category' => 'Respiratory'],
            ['medicine_name' => 'Diatabs (Attapulgite)', 'dosage' => '600mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Digestive'],
            ['medicine_name' => 'Kremil-S (Aluminum/Magnesium Hydroxide)', 'dosage' => '178mg/233mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Digestive'],
            ['medicine_name' => 'Enervon-C (Multivitamins)', 'dosage' => '500mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Vitamins'],
            ['medicine_name' => 'Medicol Advance (Ibuprofen)', 'dosage' => '400mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Pain Relief'],
            ['medicine_name' => 'Alaxan FR (Ibuprofen + Paracetamol)', 'dosage' => '200mg/325mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Pain Relief'],
            ['medicine_name' => 'Decolgen (Phenylpropanolamine + Paracetamol)', 'dosage' => '25mg/500mg', 'manufacturer' => 'Unilab', 'requiresPrescription' => false, 'category' => 'Cold & Flu'],
        ];

        $medicines = [];
        foreach ($medicinesData as $medData) {
            $medicines[] = Medicine::create($medData);
        }
        echo "[4/5] 20 Medicines created.\n";

        // ============================================
        // INVENTORY ITEMS
        // Each pharmacy gets 12-18 of the 20 medicines
        // ============================================

        // Price ranges: OTC = 5-25, Prescription = 8-50
        $otcPrices = [5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 18, 20, 22, 25];
        $rxPrices = [8, 10, 12, 14, 15, 18, 20, 22, 25, 28, 30, 35, 38, 40, 45, 50];

        // Define which medicines each pharmacy carries (indices 0-19)
        // Not all pharmacies carry all medicines — intentional gaps
        $inventoryMap = [
            // Mercury Drug - large pharmacy, carries 18/20
            0 => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17],
            // Rose Pharmacy - carries 15/20
            1 => [0, 1, 2, 3, 4, 5, 6, 9, 10, 11, 12, 14, 16, 17, 19],
            // Generika - generic focus, carries 14/20
            2 => [0, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 17, 18],
            // South Star Drug - carries 16/20
            3 => [0, 1, 2, 3, 4, 5, 7, 8, 9, 11, 12, 14, 15, 16, 18, 19],
            // TGP Pharmacy Daraga - smaller, carries 12/20
            4 => [0, 1, 3, 4, 5, 9, 10, 11, 12, 14, 16, 19],
        ];

        $inventoryCount = 0;
        $seed = 42; // Fixed seed for reproducible randomness

        foreach ($pharmacies as $pIndex => $pharmacy) {
            $medicineIndices = $inventoryMap[$pIndex];

            foreach ($medicineIndices as $mIndex) {
                $medicine = $medicines[$mIndex];
                $isRx = $medicine->requiresPrescription;

                // Generate pseudo-random stock (some should be 0 for demo)
                $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
                $stockRaw = $seed % 220; // 0-219
                // ~10% chance of 0 stock
                $stock = ($stockRaw < 22) ? 0 : (($stockRaw % 196) + 5);

                // Generate price
                $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
                if ($isRx) {
                    $price = $rxPrices[$seed % count($rxPrices)];
                } else {
                    $price = $otcPrices[$seed % count($otcPrices)];
                }

                InventoryItem::create([
                    'pharmacy_id'   => $pharmacy->id,
                    'medicine_id'   => $medicine->id,
                    'stockQuantity' => $stock,
                    'price'         => $price,
                    'status'        => $stock > 0 ? 'available' : 'out_of_stock',
                ]);
                $inventoryCount++;
            }
        }
        echo "[5/5] $inventoryCount Inventory items created.\n";

        // ============================================
        // SUMMARY
        // ============================================
        echo "\n============================================\n";
        echo "  DEMO SEEDER COMPLETED!\n";
        echo "============================================\n";
        echo "  Pharmacies:      " . Pharmacy::count() . "\n";
        echo "  Medicines:       " . Medicine::count() . "\n";
        echo "  Inventory Items: " . InventoryItem::count() . "\n";
        echo "  Users:           " . User::count() . "\n";
        echo "============================================\n";
        echo "\n  LOGIN CREDENTIALS:\n";
        echo "  Admin:      admin@medfind.com / password\n";
        echo "  Pharmacy 1: mercury@demo.com / password\n";
        echo "  Pharmacy 2: rose@demo.com / password\n";
        echo "  Pharmacy 3: generika@demo.com / password\n";
        echo "  Pharmacy 4: southstar@demo.com / password\n";
        echo "  Pharmacy 5: tgp@demo.com / password\n";
        echo "  Consumer 1: consumer1@demo.com / password\n";
        echo "  Consumer 2: consumer2@demo.com / password\n";
        echo "============================================\n\n";
    }
}
