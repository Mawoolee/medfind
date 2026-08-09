<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Pharmacy;
use App\Models\Medicine;
use App\Models\InventoryItem;
use App\Models\User;

class SuppliersAndReceivingSeeder extends Seeder
{
    public function run()
    {
        // Create a few suppliers
        $suppliers = [
            ['name' => 'Metro Pharma', 'contact_person' => 'Ana Santos', 'phone' => '09171110001', 'email' => 'sales@metropharma.test', 'address' => '1 Metro St'],
            ['name' => 'Island Distributors', 'contact_person' => 'Rafael Cruz', 'phone' => '09171110002', 'email' => 'orders@islanddist.test', 'address' => '2 Island Ave'],
            ['name' => 'Global Med Supply', 'contact_person' => 'Maya Reyes', 'phone' => '09171110003', 'email' => 'hello@globalmed.test', 'address' => '3 Global Rd'],
        ];

        foreach ($suppliers as $s) {
            Supplier::updateOrCreate(['name' => $s['name']], $s);
        }

        $this->command->info('Seeded suppliers.');

        // Find a pharmacy (created by InventoryTestSeeder) or create one
        $user = User::where('role', 'pharmacy')->first();
        if (!$user) {
            $user = User::create([ 'name' => 'Seeder Pharmacy', 'email' => 'seeder-pharmacy@example.test', 'password' => bcrypt('password'), 'role' => 'pharmacy' ]);
            $this->command->info('Created seeder user: '.$user->email);
        }

        $pharmacy = Pharmacy::where('user_id', $user->id)->first();
        if (!$pharmacy) {
            $pharmacy = Pharmacy::create([
                'pharmacy_name' => 'Seeder Pharmacy',
                'pharmacyAddress' => '99 Seeder Lane',
                'latitude' => 14.6,
                'longitude' => 120.98,
                'contactNumber' => '09171119999',
                'user_id' => $user->id,
                'status' => 'approved',
            ]);
            $this->command->info('Created pharmacy: '.$pharmacy->pharmacy_name);
        }

        // Sample shipment items
        $shipment = [
            ['medicine_name' => 'Paracetamol', 'dosage' => '500mg', 'manufacturer' => 'Acme Pharma', 'quantity' => 50, 'price' => 20.0, 'batch_number' => 'BATCH-PAR-001', 'expiry_date' => now()->addYears(2)->toDateString(), 'cold_chain' => false],
            ['medicine_name' => 'Insulin (Vial)', 'dosage' => '100IU/ml', 'manufacturer' => 'BioCare', 'quantity' => 10, 'price' => 850.0, 'batch_number' => 'BATCH-INS-010', 'expiry_date' => now()->addMonths(18)->toDateString(), 'cold_chain' => true],
            ['medicine_name' => 'Amoxicillin', 'dosage' => '250mg', 'manufacturer' => 'HealthCorp', 'quantity' => 30, 'price' => 45.0, 'batch_number' => 'BATCH-AMO-005', 'expiry_date' => now()->addYears(1)->toDateString(), 'cold_chain' => false],
        ];

        $supplier = Supplier::first();

        $processed = 0;
        foreach ($shipment as $it) {
            if (empty($it['medicine_name'])) continue;

            $medicine = Medicine::firstOrCreate(['medicine_name' => $it['medicine_name']], [
                'dosage' => $it['dosage'] ?? '',
                'manufacturer' => $it['manufacturer'] ?? '',
            ]);

            $existing = InventoryItem::where('pharmacy_id', $pharmacy->id)->where('medicine_id', $medicine->id)->first();
            $existingQty = $existing->stockQuantity ?? 0;

            $inv = InventoryItem::updateOrCreate(
                ['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id],
                [
                    'stockQuantity' => $existingQty + intval($it['quantity']),
                    'price' => $it['price'] ?? 0,
                    'batch_number' => $it['batch_number'] ?? null,
                    'expiry_date' => !empty($it['expiry_date']) ? $it['expiry_date'] : null,
                    'cold_chain' => !empty($it['cold_chain']) ? boolval($it['cold_chain']) : false,
                    'supplier_id' => $supplier->id ?? null,
                    'status' => 'available',
                ]
            );

            $processed++;
        }

        $this->command->info("Processed {$processed} shipment items into inventory for pharmacy: {$pharmacy->pharmacy_name}");
    }
}
