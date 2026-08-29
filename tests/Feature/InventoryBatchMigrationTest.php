<?php

namespace Tests\Feature;

use App\Database\Migration\LegacyInventoryBackfill;
use App\Domain\Inventory\BatchIdentity;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\StockMovement;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class InventoryBatchMigrationTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $migrationDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrationDate = CarbonImmutable::parse('2029-06-15');
    }

    /** **Validates: Requirements 2.2, 8.1-8.8, 8.10-8.13** */
    public function test_schema_and_backfill_preserve_sparse_unicode_legacy_inventory_exactly(): void
    {
        $supplier = Supplier::create(['name' => 'Fármaco 薬 Supply']);
        $item = $this->legacyItem([
            'id' => 9007,
            'stockQuantity' => 37,
            'price' => '123.40',
            'batch_number' => "  BÁTCH\t薬  01  ",
            'lot_number' => 'LÓTE-β',
            'expiry_date' => '2031-02-28',
            'cold_chain' => true,
            'supplier_id' => $supplier->id,
            'created_at' => '2024-02-29 23:59:59',
            'updated_at' => '2025-01-02 03:04:05',
        ]);

        $this->runBackfill();

        $batch = InventoryBatch::where('legacy_source_inventory_item_id', 9007)->firstOrFail();
        self::assertSame($item->id, $batch->inventory_item_id);
        self::assertSame("BÁTCH\t薬  01", $batch->batch_number);
        self::assertSame('LÓTE-β', $batch->lot_number);
        self::assertSame(BatchIdentity::key("BÁTCH\t薬  01", 'LÓTE-β'), $batch->identity_key);
        self::assertSame(37, $batch->quantity_received);
        self::assertSame(37, $batch->current_quantity);
        self::assertSame('123.40', $batch->price);
        self::assertSame($supplier->id, $batch->supplier_id);
        self::assertSame('Fármaco 薬 Supply', $batch->supplier_name);
        self::assertSame('2024-02-29', $batch->received_date->toDateString());
        self::assertTrue($batch->cold_chain);
        self::assertSame(37, $item->fresh()->stockQuantity);

        $movement = StockMovement::where('operation_id', 'legacy-backfill:9007')->firstOrFail();
        self::assertSame(0, $movement->before_quantity);
        self::assertSame(37, $movement->after_quantity);
        self::assertSame(37, $movement->quantity_delta);
        self::assertSame($batch->id, $movement->inventory_batch_id);

        self::assertTrue(Schema::hasColumns('inventory_batches', ['legacy_source_inventory_item_id', 'identity_key', 'received_reference']));
        self::assertTrue(Schema::hasColumns('stock_movements', ['operation_id', 'inventory_batch_id', 'quantity_delta']));
        self::assertTrue(Schema::hasColumn('medicines', 'cold_chain_required'));
        self::assertTrue(Schema::hasColumn('inventory_audits', 'operation_id'));
        self::assertTrue(Schema::hasColumn('controlled_substance_logs', 'operation_id'));
        self::assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    /** **Validates: Requirements 8.4, 8.6, 8.7, 8.10** */
    public function test_null_zero_and_expired_legacy_values_use_deterministic_fallbacks(): void
    {
        $zero = $this->legacyItem([
            'id' => 101,
            'stockQuantity' => 0,
            'price' => '0.00',
            'batch_number' => null,
            'lot_number' => null,
            'expiry_date' => null,
            'created_at' => null,
            'updated_at' => null,
        ]);
        $expired = $this->legacyItem([
            'id' => 303,
            'stockQuantity' => 19,
            'price' => '7.25',
            'batch_number' => '',
            'expiry_date' => '2028-01-01',
        ]);

        $this->runBackfill();

        $zeroBatch = InventoryBatch::where('legacy_source_inventory_item_id', 101)->firstOrFail();
        self::assertSame('LEGACY-101', $zeroBatch->batch_number);
        self::assertSame('legacy:101', $zeroBatch->identity_key);
        self::assertSame($this->migrationDate->toDateString(), $zeroBatch->received_date->toDateString());
        self::assertSame(0, $zeroBatch->current_quantity);

        $expiredBatch = InventoryBatch::where('legacy_source_inventory_item_id', 303)->firstOrFail();
        self::assertSame(19, $expiredBatch->quantity_received);
        self::assertSame(19, $expiredBatch->current_quantity);
        self::assertSame(0, $expired->fresh()->stockQuantity);
        self::assertSame('out_of_stock', $expired->fresh()->status);
        self::assertSame(0, $zero->fresh()->stockQuantity);
    }

    /** **Validates: Requirements 8.2, 8.3, 8.9** */
    public function test_partial_and_repeated_backfills_are_idempotent(): void
    {
        $item = $this->legacyItem([
            'id' => 777,
            'stockQuantity' => 12,
            'price' => '9.99',
            'batch_number' => 'PARTIAL-777',
            'lot_number' => 'LOT-777',
        ]);
        $existing = new InventoryBatch;
        $existing->forceFill([
            'inventory_item_id' => $item->id,
            'legacy_source_inventory_item_id' => $item->id,
            'batch_number' => 'PARTIAL-777',
            'lot_number' => 'LOT-777',
            'identity_key' => BatchIdentity::key('PARTIAL-777', 'LOT-777'),
            'quantity_received' => 12,
            'current_quantity' => 12,
            'price' => '9.99',
            'expiry_date' => null,
            'cold_chain' => false,
            'received_date' => $item->created_at->toDateString(),
            'received_reference' => 'legacy-inventory:777',
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ]);
        $existing->save();
        $createdAt = $existing->created_at;

        $this->runBackfill();
        $firstState = $this->canonicalBackfillState();
        $this->runBackfill();

        self::assertSame($firstState, $this->canonicalBackfillState());
        self::assertSame(1, InventoryBatch::where('legacy_source_inventory_item_id', 777)->count());
        self::assertSame(1, StockMovement::where('operation_id', 'legacy-backfill:777')->count());
        self::assertTrue($existing->is($existing->fresh()));
        self::assertTrue($createdAt->equalTo($existing->fresh()->created_at));
    }

    /** **Validates: Requirements 8.12** */
    public function test_injected_verification_failure_rolls_back_batches_movements_and_projection(): void
    {
        $item = $this->legacyItem(['id' => 505, 'stockQuantity' => 8, 'price' => '5.00']);

        try {
            (new LegacyInventoryBackfill)->run(DB::connection(), $this->migrationDate, static function (): void {
                throw new RuntimeException('Injected verification failure.');
            });
            self::fail('The injected verification failure must escape the backfill transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected verification failure.', $exception->getMessage());
        }

        self::assertSame(0, InventoryBatch::count());
        self::assertSame(0, StockMovement::count());
        self::assertSame(8, $item->fresh()->stockQuantity);
        self::assertSame('available', $item->fresh()->status);
    }

    public function test_stock_movements_are_immutable_through_eloquent(): void
    {
        $item = $this->legacyItem(['id' => 606, 'stockQuantity' => 3]);
        $this->runBackfill();
        $movement = StockMovement::firstOrFail();

        $this->expectException(\LogicException::class);
        $movement->update(['reason' => 'Changed']);
    }

    /** @param array<string, mixed> $attributes */
    private function legacyItem(array $attributes): InventoryItem
    {
        $pharmacy = Pharmacy::factory()->create();
        $medicine = Medicine::factory()->create();

        return InventoryItem::factory()->create(array_merge([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 5,
            'price' => '10.00',
            'status' => 'available',
            'batch_number' => null,
            'lot_number' => null,
            'expiry_date' => null,
            'cold_chain' => false,
            'supplier_id' => null,
        ], $attributes));
    }

    private function runBackfill(): void
    {
        (new LegacyInventoryBackfill)->run(DB::connection(), $this->migrationDate);
    }

    private function canonicalBackfillState(): string
    {
        return hash('sha256', json_encode([
            'batches' => DB::table('inventory_batches')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'movements' => DB::table('stock_movements')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'aggregates' => DB::table('inventory_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
