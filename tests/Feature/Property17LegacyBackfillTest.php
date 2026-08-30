<?php

namespace Tests\Feature;

use App\Database\Migration\LegacyInventoryBackfill;
use App\Domain\Inventory\BatchIdentity;
use Carbon\CarbonImmutable;
use Eris\Generators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PropertyTestCase;

final class Property17LegacyBackfillTest extends PropertyTestCase
{
    use RefreshDatabase;

    protected function shouldSeed(): bool
    {
        return false;
    }

    private CarbonImmutable $migrationDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationDate = CarbonImmutable::parse('2029-06-15');
    }

    /** **Validates: Requirements 8.1-8.6, 8.9, 8.10** */
    public function test_legacy_backfill_is_preserving_and_idempotent(): void
    {
        // Feature: pharmacy-medicine-batch-stock-management, Property 17: Legacy backfill is preserving and idempotent
        $this->forAll(Generators::choose(1, 100_000))
            ->then(function (int $seed): void {
                $this->clearGeneratedFixtures();
                $rowSpecs = $this->rowSpecs($seed);

                $pharmacyId = 100_000 + $seed;
                $supplierId = 300_000 + $seed;
                $supplierName = "Fármaco 薬 Supplier {$seed}";
                $fixtureTimestamp = $this->migrationDate->startOfDay()->toDateTimeString();
                DB::table('pharmacies')->insert([
                    'id' => $pharmacyId,
                    'pharmacy_name' => "Legacy Pharmacy {$seed}",
                    'pharmacyAddress' => 'Generated migration fixture',
                    'latitude' => '13.1400000',
                    'longitude' => '123.7400000',
                    'contactNumber' => '0000000000',
                    'status' => 'approved',
                    'user_id' => null,
                    'created_at' => $fixtureTimestamp,
                    'updated_at' => $fixtureTimestamp,
                ]);
                DB::table('suppliers')->insert([
                    'id' => $supplierId,
                    'name' => $supplierName,
                    'created_at' => $fixtureTimestamp,
                    'updated_at' => $fixtureTimestamp,
                ]);
                $snapshots = [];
                $preexistingBatchIds = [];

                foreach ($rowSpecs as $index => $spec) {
                    [$quantity, $priceCents, $batchNumber, $lotNumber, $expiryOffset, $coldChain, $hasSupplier, $parLevel, $createdOffset, $preexisting] = $spec;
                    $inventoryItemId = 1_000_000 + ($seed * 10) + $index;
                    $medicineId = 500_000 + ($seed * 10) + $index;
                    DB::table('medicines')->insert([
                        'id' => $medicineId,
                        'medicine_name' => "Legacy Medicine {$seed}-{$index}",
                        'brand_name' => null,
                        'dosage' => '10mg',
                        'manufacturer' => 'Generated manufacturer',
                        'requiresPrescription' => false,
                        'cold_chain_required' => false,
                        'category' => 'Generated',
                        'created_at' => $fixtureTimestamp,
                        'updated_at' => $fixtureTimestamp,
                    ]);
                    $price = number_format($priceCents / 100, 2, '.', '');
                    $expiryDate = $expiryOffset === null
                        ? null
                        : $this->migrationDate->addDays($expiryOffset)->toDateString();
                    $createdAt = $createdOffset === null
                        ? null
                        : $this->migrationDate->addDays($createdOffset)->setTime(4, 5, 6)->toDateTimeString();
                    $updatedAt = $createdAt === null
                        ? null
                        : CarbonImmutable::parse($createdAt)->addHours(7)->toDateTimeString();
                    $linkedSupplierId = $hasSupplier ? $supplierId : null;

                    DB::table('inventory_items')->insert([
                        'id' => $inventoryItemId,
                        'pharmacy_id' => $pharmacyId,
                        'medicine_id' => $medicineId,
                        'stockQuantity' => $quantity,
                        'price' => $price,
                        'status' => $quantity === 0 ? 'out_of_stock' : 'available',
                        'batch_number' => $batchNumber,
                        'lot_number' => $lotNumber,
                        'expiry_date' => $expiryDate,
                        'cold_chain' => $coldChain,
                        'par_level' => $parLevel,
                        'supplier_id' => $linkedSupplierId,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ]);

                    $snapshots[$inventoryItemId] = [
                        'pharmacy_id' => $pharmacyId,
                        'medicine_id' => $medicineId,
                        'quantity' => $quantity,
                        'price' => $price,
                        'batch_number' => $batchNumber,
                        'lot_number' => $lotNumber,
                        'expiry_date' => $expiryDate,
                        'cold_chain' => $coldChain,
                        'par_level' => $parLevel,
                        'supplier_id' => $linkedSupplierId,
                        'supplier_name' => $linkedSupplierId === null ? null : $supplierName,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ];

                    if ($index === 0 || $preexisting) {
                        $batchId = 4_000_000 + ($seed * 10) + $index;
                        $this->insertExistingBackfillBatch($batchId, $inventoryItemId, $snapshots[$inventoryItemId]);
                        $preexistingBatchIds[$inventoryItemId] = $batchId;
                    }
                }

                $this->runBackfill();

                foreach ($snapshots as $inventoryItemId => $snapshot) {
                    $item = DB::table('inventory_items')->where('id', $inventoryItemId)->first();
                    $batches = DB::table('inventory_batches')
                        ->where('legacy_source_inventory_item_id', $inventoryItemId)
                        ->get();

                    self::assertNotNull($item);
                    self::assertCount(1, $batches);
                    $batch = $batches->first();
                    self::assertNotNull($batch);

                    self::assertSame($inventoryItemId, (int) $item->id);
                    self::assertSame($snapshot['pharmacy_id'], (int) $item->pharmacy_id);
                    self::assertSame($snapshot['medicine_id'], (int) $item->medicine_id);
                    self::assertSame($snapshot['batch_number'], $item->batch_number);
                    self::assertSame($snapshot['lot_number'], $item->lot_number);
                    self::assertSame($snapshot['expiry_date'], $item->expiry_date);
                    self::assertSame($snapshot['cold_chain'], (bool) $item->cold_chain);
                    self::assertSame($snapshot['supplier_id'], $item->supplier_id === null ? null : (int) $item->supplier_id);
                    self::assertSame($snapshot['price'], $this->decimal($item->price));

                    self::assertSame($inventoryItemId, (int) $batch->inventory_item_id);
                    self::assertSame($inventoryItemId, (int) $batch->legacy_source_inventory_item_id);
                    self::assertSame($this->displayBatchNumber($snapshot['batch_number'], $inventoryItemId), $batch->batch_number);
                    self::assertSame($snapshot['lot_number'], $batch->lot_number);
                    self::assertSame($this->identityKey($snapshot['batch_number'], $snapshot['lot_number'], $inventoryItemId), $batch->identity_key);
                    self::assertSame($snapshot['quantity'], (int) $batch->quantity_received);
                    self::assertSame($snapshot['quantity'], (int) $batch->current_quantity);
                    self::assertSame($snapshot['price'], $this->decimal($batch->price));
                    self::assertSame($snapshot['supplier_id'], $batch->supplier_id === null ? null : (int) $batch->supplier_id);
                    self::assertSame($snapshot['supplier_name'], $batch->supplier_name);
                    self::assertSame($snapshot['expiry_date'], $batch->expiry_date);
                    self::assertSame($snapshot['cold_chain'], (bool) $batch->cold_chain);
                    self::assertSame($this->receivedDate($snapshot['created_at']), $batch->received_date);
                    self::assertSame("legacy-inventory:{$inventoryItemId}", $batch->received_reference);
                    self::assertSame($this->timestamp($snapshot['created_at']), $this->timestamp($batch->created_at));
                    self::assertSame($this->timestamp($snapshot['updated_at'] ?? $snapshot['created_at']), $this->timestamp($batch->updated_at));

                    if (isset($preexistingBatchIds[$inventoryItemId])) {
                        self::assertSame($preexistingBatchIds[$inventoryItemId], (int) $batch->id);
                    }

                    $movements = DB::table('stock_movements')
                        ->where('operation_id', "legacy-backfill:{$inventoryItemId}")
                        ->get();
                    self::assertCount(1, $movements);
                    self::assertSame((int) $batch->id, (int) $movements->first()->inventory_batch_id);
                    self::assertSame($snapshot['quantity'], (int) $movements->first()->after_quantity);

                    $expectedAvailable = $snapshot['expiry_date'] === null
                        || $snapshot['expiry_date'] >= $this->migrationDate->toDateString()
                            ? $snapshot['quantity']
                            : 0;
                    self::assertSame($expectedAvailable, (int) $item->stockQuantity);
                }

                $firstState = $this->canonicalBackfillState();
                $this->runBackfill();

                self::assertSame($firstState, $this->canonicalBackfillState());
                self::assertSame(count($snapshots), DB::table('inventory_batches')->count());
                self::assertSame(count($snapshots), DB::table('stock_movements')->count());
            });
    }

    /**
     * Expand one shrinkable Eris seed into a small valid legacy row set without
     * constructing a deeply nested generator tree.
     *
     * @return array<int, array{int, int, ?string, ?string, ?int, bool, bool, int, ?int, bool}>
     */
    private function rowSpecs(int $seed): array
    {
        $batchNumbers = [null, '', '   ', '  BÁTCH 薬 01  ', " BATCH\t42 "];
        $lotNumbers = [null, 'LOT-α', " LÓTE\t薬 "];
        $expiryOffsets = [null, -400, -1, 0, 1, 400];
        $createdOffsets = [null, -1200, -1, 0, 1200];
        $rows = [];

        for ($index = 0; $index < 3; $index++) {
            $factor = $index + 1;
            $rows[] = [
                ($seed * ($factor + 2)) % 501,
                ($seed * ($factor * 137)) % 100_001,
                $batchNumbers[($seed + $index) % count($batchNumbers)],
                $lotNumbers[(intdiv($seed, $factor) + $index) % count($lotNumbers)],
                $expiryOffsets[(intdiv($seed, $factor + 1) + $index) % count($expiryOffsets)],
                (($seed >> $index) & 1) === 1,
                (($seed + $index) % 2) === 0,
                ($seed * ($factor + 7)) % 101,
                $createdOffsets[(intdiv($seed, $factor + 2) + $index) % count($createdOffsets)],
                (($seed + $index) % 3) === 0,
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $snapshot */
    private function insertExistingBackfillBatch(int $batchId, int $inventoryItemId, array $snapshot): void
    {
        DB::table('inventory_batches')->insert([
            'id' => $batchId,
            'inventory_item_id' => $inventoryItemId,
            'legacy_source_inventory_item_id' => $inventoryItemId,
            'batch_number' => $this->displayBatchNumber($snapshot['batch_number'], $inventoryItemId),
            'lot_number' => $snapshot['lot_number'],
            'identity_key' => $this->identityKey($snapshot['batch_number'], $snapshot['lot_number'], $inventoryItemId),
            'quantity_received' => $snapshot['quantity'],
            'current_quantity' => $snapshot['quantity'],
            'price' => $snapshot['price'],
            'supplier_id' => $snapshot['supplier_id'],
            'supplier_name' => $snapshot['supplier_name'],
            'expiry_date' => $snapshot['expiry_date'],
            'cold_chain' => $snapshot['cold_chain'],
            'received_date' => $this->receivedDate($snapshot['created_at']),
            'received_reference' => "legacy-inventory:{$inventoryItemId}",
            'created_by' => null,
            'created_at' => $snapshot['created_at'],
            'updated_at' => $snapshot['updated_at'] ?? $snapshot['created_at'],
        ]);
    }

    private function runBackfill(): void
    {
        (new LegacyInventoryBackfill)->run(DB::connection(), $this->migrationDate);
    }

    private function clearGeneratedFixtures(): void
    {
        DB::table('stock_movements')->delete();
        DB::table('inventory_batches')->delete();
        DB::table('inventory_items')->delete();
        DB::table('suppliers')->delete();
        DB::table('medicines')->delete();
        DB::table('pharmacies')->delete();
    }

    private function displayBatchNumber(?string $batchNumber, int $inventoryItemId): string
    {
        if ($batchNumber === null || preg_match('/\S/u', $batchNumber) !== 1) {
            return BatchIdentity::legacyBatchNumber($inventoryItemId);
        }

        return (string) preg_replace('/^\s+|\s+$/u', '', $batchNumber);
    }

    private function identityKey(?string $batchNumber, ?string $lotNumber, int $inventoryItemId): string
    {
        if ($batchNumber === null || preg_match('/\S/u', $batchNumber) !== 1) {
            return BatchIdentity::legacy($inventoryItemId);
        }

        return BatchIdentity::key($this->displayBatchNumber($batchNumber, $inventoryItemId), $lotNumber);
    }

    private function receivedDate(?string $createdAt): string
    {
        return $createdAt === null
            ? $this->migrationDate->toDateString()
            : CarbonImmutable::parse($createdAt)->toDateString();
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function timestamp(mixed $value): ?string
    {
        return $value === null
            ? null
            : CarbonImmutable::parse((string) $value)->format('Y-m-d H:i:s.u');
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function canonicalBackfillState(): array
    {
        return [
            'aggregates' => DB::table('inventory_items')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'batches' => DB::table('inventory_batches')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'movements' => DB::table('stock_movements')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ];
    }
}
