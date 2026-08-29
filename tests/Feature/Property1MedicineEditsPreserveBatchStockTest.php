<?php

namespace Tests\Feature;

use App\Domain\Inventory\BatchIdentity;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\StockMovement;
use App\Services\MedicineMasterService;
use Eris\Generators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PropertyTestCase;

final class Property1MedicineEditsPreserveBatchStockTest extends PropertyTestCase
{
    use RefreshDatabase;

    /** **Validates: Requirements 1.1, 2.5** */
    public function test_medicine_edits_preserve_batch_stock(): void
    {
        // Feature: pharmacy-medicine-batch-stock-management, Property 1: Medicine edits preserve batch stock
        $pharmacy = Pharmacy::factory()->create();
        $service = app(MedicineMasterService::class);

        $this->forAll(
            Generators::choose(1, 100_000),
            Generators::choose(0, 100),
        )->then(function (int $seed, int $parLevel) use ($pharmacy, $service): void {
            $this->clearGeneratedFixtures();

            $medicine = Medicine::factory()->create([
                'medicine_name' => "Original Generic {$seed}",
                'brand_name' => "Original Brand {$seed}",
                'dosage' => '5mg',
                'category' => 'Original category',
                'manufacturer' => 'Original manufacturer',
                'requiresPrescription' => false,
                'cold_chain_required' => false,
            ]);
            $batchCount = 1 + ($seed % 4);
            $batchSpecs = [];
            $availableQuantity = 0;

            for ($index = 0; $index < $batchCount; $index++) {
                $quantityReceived = 1 + (($seed * ($index + 3)) % 200);
                $currentQuantity = ($seed + ($index * 17)) % ($quantityReceived + 1);
                $batchSpecs[] = [$quantityReceived, $currentQuantity];
                $availableQuantity += $currentQuantity;
            }

            $aggregate = InventoryItem::factory()->create([
                'pharmacy_id' => $pharmacy->id,
                'medicine_id' => $medicine->id,
                'stockQuantity' => $availableQuantity,
                'price' => number_format((($seed % 50_000) + 1) / 100, 2, '.', ''),
                'status' => $availableQuantity === 0 ? 'out_of_stock' : 'available',
                'par_level' => 3,
            ]);

            foreach ($batchSpecs as $index => [$quantityReceived, $currentQuantity]) {
                $batchNumber = "BATCH-{$aggregate->id}-{$index}";
                $lotNumber = $index % 2 === 0 ? "LOT-{$seed}-{$index}" : null;
                $batch = InventoryBatch::factory()->create([
                    'inventory_item_id' => $aggregate->id,
                    'batch_number' => $batchNumber,
                    'lot_number' => $lotNumber,
                    'identity_key' => BatchIdentity::key($batchNumber, $lotNumber),
                    'quantity_received' => $quantityReceived,
                    'current_quantity' => $currentQuantity,
                    'price' => number_format((($seed + $index + 1) % 50_000) / 100, 2, '.', ''),
                    'expiry_date' => $index % 3 === 0 ? null : now()->addDays($index + 1)->toDateString(),
                    'received_date' => now()->subDays($batchCount - $index)->toDateString(),
                ]);

                StockMovement::factory()->create([
                    'operation_id' => "property-1-receipt-{$aggregate->id}-{$index}",
                    'inventory_item_id' => $aggregate->id,
                    'inventory_batch_id' => $batch->id,
                    'type' => 'receipt',
                    'before_quantity' => 0,
                    'after_quantity' => $quantityReceived,
                    'quantity_delta' => $quantityReceived,
                    'reason' => null,
                ]);

                if ($currentQuantity !== $quantityReceived) {
                    StockMovement::factory()->create([
                        'operation_id' => "property-1-decrease-{$aggregate->id}-{$index}",
                        'inventory_item_id' => $aggregate->id,
                        'inventory_batch_id' => $batch->id,
                        'type' => 'fefo_decrease',
                        'before_quantity' => $quantityReceived,
                        'after_quantity' => $currentQuantity,
                        'quantity_delta' => $currentQuantity - $quantityReceived,
                        'reason' => 'Generated prior stock decrease',
                    ]);
                }
            }

            $preservedAggregate = $aggregate->only([
                'id',
                'pharmacy_id',
                'medicine_id',
                'stockQuantity',
                'price',
                'status',
                'expiry_date',
                'batch_number',
                'lot_number',
                'cold_chain',
                'supplier_id',
            ]);
            $batchesBefore = $this->batchState($aggregate->id);
            $movementsBefore = $this->movementState($aggregate->id);

            $updated = $service->updateForPharmacy($aggregate, [
                'medicine_name' => "Edited Generic {$seed}-{$aggregate->id}",
                'brand_name' => $seed % 3 === 0 ? null : "Edited Brand {$seed}",
                'dosage' => (1 + ($seed % 500)).'mg',
                'category' => $seed % 2 === 0 ? 'Edited category' : null,
                'manufacturer' => "Edited Manufacturer {$seed}",
                'requiresPrescription' => $seed % 2 === 0,
                'cold_chain_required' => $seed % 5 === 0,
            ], $parLevel);

            self::assertSame($preservedAggregate, $updated->only(array_keys($preservedAggregate)));
            self::assertSame($parLevel, (int) $updated->par_level);
            self::assertSame("Edited Generic {$seed}-{$aggregate->id}", $updated->medicine->medicine_name);
            self::assertSame($batchesBefore, $this->batchState($aggregate->id));
            self::assertSame($movementsBefore, $this->movementState($aggregate->id));
        });
    }

    private function clearGeneratedFixtures(): void
    {
        DB::table('stock_movements')->delete();
        DB::table('inventory_batches')->delete();
        DB::table('inventory_items')->delete();
        DB::table('medicines')->delete();

        gc_collect_cycles();
    }

    /** @return array<int, array<string, mixed>> */
    private function batchState(int $aggregateId): array
    {
        return DB::table('inventory_batches')
            ->where('inventory_item_id', $aggregateId)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $batch): array => (array) $batch)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function movementState(int $aggregateId): array
    {
        return DB::table('stock_movements')
            ->where('inventory_item_id', $aggregateId)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $movement): array => (array) $movement)
            ->all();
    }
}
