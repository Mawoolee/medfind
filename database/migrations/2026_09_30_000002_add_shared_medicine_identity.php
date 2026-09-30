<?php

use App\Support\MedicineIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->string('identity_key', 64)->nullable()->unique();
        });

        $groups = [];
        DB::table('medicines')->orderBy('id')->chunkById(200, function ($medicines) use (&$groups): void {
            foreach ($medicines as $medicine) {
                $key = MedicineIdentity::key(
                    $medicine->medicine_name,
                    $medicine->brand_name ?? null,
                    $medicine->dosage
                );
                $groups[$key][] = $medicine;
            }
        });

        foreach ($groups as $key => $medicines) {
            $canonical = array_shift($medicines);
            $categories = [];
            $requiresPrescription = (bool) $canonical->requiresPrescription;
            $coldChainRequired = (bool) ($canonical->cold_chain_required ?? false);

            foreach ([$canonical, ...$medicines] as $medicine) {
                $storedCategories = json_decode($medicine->categories ?? '[]', true);
                foreach (is_array($storedCategories) ? $storedCategories : [] as $category) {
                    if (is_string($category) && trim($category) !== '') {
                        $categories[mb_strtolower(trim($category), 'UTF-8')] = trim($category);
                    }
                }
                if (is_string($medicine->category) && trim($medicine->category) !== '') {
                    $category = trim($medicine->category);
                    $categories[mb_strtolower($category, 'UTF-8')] = $category;
                }
                $requiresPrescription = $requiresPrescription || (bool) $medicine->requiresPrescription;
                $coldChainRequired = $coldChainRequired || (bool) ($medicine->cold_chain_required ?? false);
            }

            foreach ($medicines as $duplicate) {
                $items = DB::table('inventory_items')
                    ->where('medicine_id', $duplicate->id)
                    ->orderBy('id')
                    ->get(['id', 'pharmacy_id']);

                foreach ($items as $item) {
                    $alreadyLinked = DB::table('inventory_items')
                        ->where('medicine_id', $canonical->id)
                        ->where('pharmacy_id', $item->pharmacy_id)
                        ->exists();

                    if (! $alreadyLinked) {
                        DB::table('inventory_items')
                            ->where('id', $item->id)
                            ->update(['medicine_id' => $canonical->id]);
                    }
                }

                $stillReferenced = DB::table('inventory_items')
                    ->where('medicine_id', $duplicate->id)
                    ->exists();

                if (! $stillReferenced) {
                    DB::table('medicines')->where('id', $duplicate->id)->delete();
                }
            }

            DB::table('medicines')
                ->where('id', $canonical->id)
                ->update([
                    'identity_key' => $key,
                    'category' => array_values($categories)[0] ?? null,
                    'categories' => json_encode(array_values($categories), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'requiresPrescription' => $requiresPrescription,
                    'cold_chain_required' => $coldChainRequired,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->dropUnique(['identity_key']);
            $table->dropColumn('identity_key');
        });
    }
};
