<?php

namespace Tests\Feature;

use App\Domain\Inventory\BatchIdentity;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Services\MedicineMasterService;
use Eris\Generators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PropertyTestCase;

final class Property1MedicineEditsPreserveBatchStockTest extends PropertyTestCase
{
    use RefreshDatabase;

    protected function shouldSeed(): bool
    {
        return false;
    }

    /** **Validates: Requirements 1.1, 2.5** */
    public function test_medicine_edits_preserve_batch_stock(): void
    {
        // Feature: pharmacy-medicine-batch-stock-management, Property 1: Medicine edits preserve batch stock
        $pharmacy = Pharmacy::factory()->create();
        $medicine = Medicine::query()->create([
            'medicine_name' => 'Reusable Generic',
            'brand_name' => 'Reusable Brand',
            'dosage' => '5mg',
            'category' => 'Original category',
            'manufacturer' => 'Original manufacturer',
            'requiresPrescription' => false,
            'cold_chain_required' => false,
        ]);
        $aggregate = InventoryItem::query()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 0,
            'price' => '0.00',
            'status' => 'out_of_stock',
            'par_level' => 3,
        ]);
        $service = app(MedicineMasterService::class);
        $iterationsSinceGarbageCollection = 0;

        $this->forAll(
            Generators::choose(1, 100_000),
            Generators::choose(0, 100),
        )->then(function (int $seed, int $parLevel) use (
            $aggregate,
            $service,
            &$iterationsSinceGarbageCollection,
        ): void {
            $this->assertGeneratedEditPreservesStock($aggregate, $service, $seed, $parLevel);

            if (++$iterationsSinceGarbageCollection === 10) {
                gc_collect_cycles();
                $iterationsSinceGarbageCollection = 0;
            }
        });
    }

    private function assertGeneratedEditPreservesStock(
        InventoryItem $aggregate,
        MedicineMasterService $service,
        int $seed,
        int $parLevel,
    ): void {
        $updated = null;

        try {
            $this->resetReusableFixture($aggregate, $seed);

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
        } finally {
            $updated?->unsetRelations();
            $aggregate->unsetRelations();
            $this->deleteGeneratedChildren($aggregate->id);

            unset($batchesBefore, $movementsBefore, $preservedAggregate, $updated);
        }
    }

    private function resetReusableFixture(InventoryItem $aggregate, int $seed): void
    {
        $this->deleteGeneratedChildren($aggregate->id);

        $batchCount = 2 + ($seed % 3);
        $availableQuantity = 0;
        $timestamp = now()->toDateTimeString();

        DB::table('medicines')
            ->where('id', $aggregate->medicine_id)
            ->update([
                'medicine_name' => "Original Generic {$seed}",
                'brand_name' => "Original Brand {$seed}",
                'dosage' => '5mg',
                'category' => 'Original category',
                'manufacturer' => 'Original manufacturer',
                'requiresPrescription' => false,
                'cold_chain_required' => false,
                'updated_at' => $timestamp,
            ]);

        for ($index = 0; $index < $batchCount; $index++) {
            $quantityReceived = 1 + (($seed * ($index + 3)) % 200);
            $currentQuantity = ($seed + ($index * 17)) % ($quantityReceived + 1);
            $availableQuantity += $currentQuantity;
            $batchNumber = "BATCH-{$aggregate->id}-{$seed}-{$index}";
            $lotNumber = $index % 2 === 0 ? "LOT-{$seed}-{$index}" : null;
            $receivedReference = "PROPERTY-1-{$seed}-{$index}";
            $batchId = DB::table('inventory_batches')->insertGetId([
                'inventory_item_id' => $aggregate->id,
                'legacy_source_inventory_item_id' => null,
                'batch_number' => $batchNumber,
                'lot_number' => $lotNumber,
                'identity_key' => BatchIdentity::key($batchNumber, $lotNumber),
                'quantity_received' => $quantityReceived,
                'current_quantity' => $currentQuantity,
                'price' => number_format((($seed + $index + 1) % 50_000) / 100, 2, '.', ''),
                'supplier_id' => null,
                'supplier_name' => null,
                'expiry_date' => $index % 3 === 0 ? null : now()->addDays($index + 1)->toDateString(),
                'cold_chain' => false,
                'received_date' => now()->subDays($batchCount - $index)->toDateString(),
                'received_reference' => $receivedReference,
                'created_by' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            DB::table('stock_movements')->insert([
                'operation_id' => "property-1-receipt-{$seed}-{$index}",
                'inventory_item_id' => $aggregate->id,
                'inventory_batch_id' => $batchId,
                'type' => 'receipt',
                'before_quantity' => 0,
                'after_quantity' => $quantityReceived,
                'quantity_delta' => $quantityReceived,
                'reason' => null,
                'reference_type' => null,
                'reference_id' => null,
                'received_reference' => $receivedReference,
                'user_id' => null,
                'created_at' => $timestamp,
            ]);

            if ($currentQuantity !== $quantityReceived) {
                DB::table('stock_movements')->insert([
                    'operation_id' => "property-1-decrease-{$seed}-{$index}",
                    'inventory_item_id' => $aggregate->id,
                    'inventory_batch_id' => $batchId,
                    'type' => 'fefo_decrease',
                    'before_quantity' => $quantityReceived,
                    'after_quantity' => $currentQuantity,
                    'quantity_delta' => $currentQuantity - $quantityReceived,
                    'reason' => 'Generated prior stock decrease',
                    'reference_type' => null,
                    'reference_id' => null,
                    'received_reference' => null,
                    'user_id' => null,
                    'created_at' => $timestamp,
                ]);
            }
        }

        DB::table('inventory_items')
            ->where('id', $aggregate->id)
            ->update([
                'stockQuantity' => $availableQuantity,
                'price' => number_format((($seed % 50_000) + 1) / 100, 2, '.', ''),
                'status' => $availableQuantity === 0 ? 'out_of_stock' : 'available',
                'par_level' => 3,
                'updated_at' => $timestamp,
            ]);

        $aggregate->refresh();
    }

    private function deleteGeneratedChildren(int $aggregateId): void
    {
        DB::table('stock_movements')->where('inventory_item_id', $aggregateId)->delete();
        DB::table('inventory_batches')->where('inventory_item_id', $aggregateId)->delete();
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
