<?php

namespace Tests\Feature;

use App\Domain\Inventory\AggregateSynchronizer;
use App\Domain\Inventory\BatchIdentity;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\FEFOAllocator;
use App\Models\InventoryAudit;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReconciliationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_expiry_boundary_reconciliation_records_one_system_attributed_audit(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-11 00:10:00', config('app.timezone')));

        $expiringAggregate = InventoryItem::factory()->create([
            'stockQuantity' => 10,
            'price' => '18.50',
            'status' => 'available',
            'par_level' => 4,
        ]);
        $stableAggregate = InventoryItem::factory()->create([
            'stockQuantity' => 3,
            'price' => '18.50',
            'status' => 'available',
            'par_level' => 0,
        ]);

        $this->batch($expiringAggregate, 'EXPIRED', 7, '2025-01-10', '2025-01-01');
        $this->batch($expiringAggregate, 'AVAILABLE', 3, '2025-02-01', '2025-01-02');
        $this->batch($stableAggregate, 'STABLE', 3, null, '2025-01-02');

        $this->artisan('inventory:reconcile-batches', ['--chunk' => 1])->assertSuccessful();

        self::assertSame(3, $expiringAggregate->fresh()->stockQuantity);
        self::assertSame(3, $stableAggregate->fresh()->stockQuantity);

        $audits = InventoryAudit::query()->get();

        self::assertCount(1, $audits, 'Only the aggregate whose available stock changed should be audited.');

        $audit = $audits->sole();

        self::assertSame($expiringAggregate->id, $audit->inventory_item_id);
        self::assertNull($audit->user_id, 'A reconciliation change is system attributed.');
        self::assertSame(10, $audit->before_quantity);
        self::assertSame(3, $audit->after_quantity);
        self::assertSame(AggregateSynchronizer::RECONCILIATION_REASON, $audit->notes);
        self::assertNotNull($audit->operation_id);
        self::assertSame(
            0,
            StockMovement::query()->count(),
            'Reconciliation changes availability through expiry, not batch quantities.',
        );
    }

    public function test_reconciliation_reruns_create_no_additional_records(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-11 00:10:00', config('app.timezone')));

        $aggregate = InventoryItem::factory()->create([
            'stockQuantity' => 10,
            'price' => '18.50',
            'status' => 'available',
            'par_level' => 4,
        ]);
        $this->batch($aggregate, 'EXPIRED', 7, '2025-01-10', '2025-01-01');
        $this->batch($aggregate, 'AVAILABLE', 3, '2025-02-01', '2025-01-02');

        $synchronizer = app(AggregateSynchronizer::class);
        $asOf = CarbonImmutable::parse('2025-01-11');

        $first = $synchronizer->synchronizeChunk(1, $asOf);
        $auditsAfterFirstRun = InventoryAudit::query()->pluck('id')->all();

        $second = $synchronizer->synchronizeChunk(1, $asOf);
        $third = $synchronizer->reconcileLocked($aggregate->fresh(), $asOf);

        self::assertSame(1, $first->updated);
        self::assertSame(0, $second->updated);
        self::assertSame(1, $second->processed);
        self::assertSame(3, $third->stockQuantity);
        self::assertCount(1, $auditsAfterFirstRun);
        self::assertSame(
            $auditsAfterFirstRun,
            InventoryAudit::query()->pluck('id')->all(),
            'Repeated reconciliation without a real change must not create audits.',
        );
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_reconciliation_audits_a_drift_correction_outside_any_stock_operation(): void
    {
        $asOf = CarbonImmutable::parse('2025-03-01');
        $aggregate = InventoryItem::factory()->create([
            'stockQuantity' => 5,
            'price' => '10.00',
            'status' => 'available',
            'par_level' => 0,
        ]);
        $this->batch($aggregate, 'DRIFTED', 9, null, '2025-02-01');

        $reconciled = app(AggregateSynchronizer::class)->reconcileLocked($aggregate, $asOf);

        self::assertSame(9, $reconciled->stockQuantity);

        $audit = InventoryAudit::query()->sole();

        self::assertSame($aggregate->id, $audit->inventory_item_id);
        self::assertNull($audit->user_id);
        self::assertSame(5, $audit->before_quantity);
        self::assertSame(9, $audit->after_quantity);
        self::assertSame(AggregateSynchronizer::RECONCILIATION_REASON, $audit->notes);
    }

    public function test_synchronization_inside_a_stock_operation_records_only_the_operation_audit(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 12:00:00');

        $actor = User::factory()->create();
        $pharmacy = Pharmacy::factory()->create();
        $aggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'stockQuantity' => 999,
            'price' => '10.00',
            'par_level' => 0,
        ]);
        $this->batch($aggregate, 'EXPIRED', 4, '2025-06-09', '2025-01-01');
        $this->batch($aggregate, 'AVAILABLE', 6, '2025-07-01', '2025-05-01');

        $result = app(FEFOAllocator::class)->decrease(
            $pharmacy,
            $aggregate,
            2,
            new StockOperationContext(
                type: 'fefo_decrease',
                actorId: $actor->id,
                reason: 'Dispensed stock',
                operationId: 'stock-operation-audit',
            ),
        );

        self::assertSame(4, $aggregate->fresh()->stockQuantity);

        $audit = InventoryAudit::query()->sole();

        self::assertSame($result->audit?->id, $audit->id);
        self::assertSame('stock-operation-audit', $audit->operation_id);
        self::assertSame('Dispensed stock', $audit->notes);
        self::assertSame($actor->id, $audit->user_id);
        self::assertSame(6, $audit->before_quantity);
        self::assertSame(4, $audit->after_quantity);
        self::assertSame(
            0,
            InventoryAudit::query()->where('notes', AggregateSynchronizer::RECONCILIATION_REASON)->count(),
            'A stock operation must not add a reconciliation audit on top of its own.',
        );
    }

    private function batch(
        InventoryItem $aggregate,
        string $batchNumber,
        int $quantity,
        ?string $expiryDate,
        string $receivedDate,
    ): InventoryBatch {
        return InventoryBatch::factory()->create([
            'inventory_item_id' => $aggregate->id,
            'batch_number' => $batchNumber,
            'lot_number' => null,
            'identity_key' => BatchIdentity::key($batchNumber, null),
            'quantity_received' => $quantity,
            'current_quantity' => $quantity,
            'price' => '10.00',
            'expiry_date' => $expiryDate,
            'received_date' => $receivedDate,
        ]);
    }
}
