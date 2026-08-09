<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SearchLogSeeder extends Seeder
{
    /**
     * Seed the search_logs table with demo data so the
     * pharmacy dashboard "Searches" cards have real content.
     */
    public function run(): void
    {
        $pharmacyIds = Pharmacy::pluck('id')->all();

        if (empty($pharmacyIds)) {
            echo "⚠️ No pharmacies found — skipping search log seeding.\n";
            return;
        }

        $queries = [
            'Paracetamol',
            'Amoxicillin',
            'Ibuprofen',
            'Cetirizine',
            'Loperamide',
            'Mefenamic Acid',
            'Losartan',
            'Metformin',
            'Ascorbic Acid',
        ];

        $now = now();
        $rows = [];

        // Generate ~60 logs spread over the past 14 days so the
        // "Searches Today", "This Week", and "Total" cards all show data.
        for ($i = 0; $i < 60; $i++) {
            $daysAgo = rand(0, 13);
            $createdAt = $now->copy()->subDays($daysAgo)->subHours(rand(0, 12))->subMinutes(rand(0, 59));
            $rows[] = [
                'pharmacy_id' => $pharmacyIds[array_rand($pharmacyIds)],
                'query' => $queries[array_rand($queries)],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        DB::table('search_logs')->insert($rows);

        echo "✅ Seeded " . count($rows) . " search logs across " . count($pharmacyIds) . " pharmacies.\n";
    }
}
