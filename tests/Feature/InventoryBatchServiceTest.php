<?php

namespace Tests\Feature;

use App\Domain\Inventory\Data\BatchMetadataData;
use App\Domain\Inventory\Data\BatchReceiptData;
use App\Domain\Inventory\Exceptions\ColdChainRequired;
use App\Domain\Inventory\Exceptions\DuplicateBatchIdentity;
use App\Domain\Inventory\Exceptions\ForeignInventoryRecord;
use App\Domain\Inventory\InventoryBatchService;
use App\Events\InventoryUpdated;
use App\Models\InventoryAudit;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_receive_preserves_batch_data_resolves_supplier_and_records_synchronized_stock(): void
    {
        Event::fake([InventoryUpdated::class]);
        CarbonImmutable::setTestNow('2025-03-10 09:30:00');
        $actor = User::factory()->create();
        $pharmacy = Pharmacy::factory()->create(['user_id' => $actor->id]);
        $aggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'stockQuantity' => 91,
            'price' => '2.00',
        ]);
        $supplier = Supplier::query()->create(['name' => 'Acme Medical']);

        $batch = app(InventoryBatchService::class)->receive(
            $pharmacy,
            $aggregate,
            $this->receipt(
                batchNumber: '  BATCH-100  ',
                lotNumber: '  LOT-9  ',
                quantity: 12,
                price: '19.5',
                supplierName: '  ACME   Medical  ',
                reference: '  PO-100  ',
                actorId: $actor->id,
            ),
        );

        $this->assertSame('BATCH-100', $batch->batch_number);
        $this->assertSame('LOT-9', $batch->lot_number);
        $this->assertSame(12, $batch->quantity_received);
        $this->assertSame(12, $batch->current_quantity);
        $this->assertSame('19.50', $batch->price);
        $this->assertSame($supplier->id, $batch->supplier_id);
        $this->assertSame('ACME   Medical', $batch->supplier_name);
        $this->assertSame('2025-03-10', $batch->received_date->toDateString());
        $this->assertSame('PO-100', $batch->received_reference);
        $this->assertSame(1, Supplier::query()->count());

        $movement = StockMovement::query()->sole();
        $this->assertSame('receipt', $movement->type);
        $this->assertSame(0, $movement->before_quantity);
        $this->assertSame(12, $movement->after_quantity);
        $this->assertSame(12, $movement->quantity_delta);
        $this->assertSame($actor->id, $movement->user_id);
        $this->assertSame('PO-100', $movement->received_reference);

        $audit = InventoryAudit::query()->sole();
        $this->assertSame(0, $audit->before_quantity);
        $this->assertSame(12, $audit->after_quantity);
        $this->assertSame($movement->operation_id, $audit->operation_id);
        $this->assertSame(12, $aggregate->fresh()->stockQuantity);
        $this->assertSame(19.5, (float) $aggregate->fresh()->price);
        Event::assertDispatchedTimes(InventoryUpdated::class, 1);
    }

    public function test_receive_creates_unmatched_supplier_and_keeps_distinct_deliveries(): void
    {
        Event::fake([InventoryUpdated::class]);
        CarbonImmutable::setTestNow('2025-04-01');
        $pharmacy = Pharmacy::factory()->create();
        $aggregate = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $service = app(InventoryBatchService::class);

        $first = $service->receive(
            $pharmacy,
            $aggregate,
            $this->receipt('BATCH-A', 'LOT-1', 5, '8.25', ' New Supplier '),
        );
        $second = $service->receive(
            $pharmacy,
            $aggregate,
            $this->receipt('BATCH-B', 'LOT-1', 7, '9.25', 'new   supplier'),
        );

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(5, $first->fresh()->current_quantity);
        $this->assertSame(7, $second->current_quantity);
        $this->assertSame($first->supplier_id, $second->supplier_id);
        $this->assertSame(1, Supplier::query()->count());
        $this->assertSame(12, $aggregate->fresh()->stockQuantity);
        $this->assertSame(2, InventoryBatch::query()->count());
        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_duplicate_cold_chain_and_foreign_receipts_are_rejected_without_stock_changes(): void
    {
        Event::fake([InventoryUpdated::class]);
        CarbonImmutable::setTestNow('2025-05-01');
        $pharmacy = Pharmacy::factory()->create();
        $otherPharmacy = Pharmacy::factory()->create();
        $aggregate = InventoryItem::factory()
            ->for(Medicine::factory()->coldChainRequired(), 'medicine')
            ->create(['pharmacy_id' => $pharmacy->id]);
        $service = app(InventoryBatchService::class);

        try {
            $service->receive(
                $pharmacy,
                $aggregate,
                $this->receipt('COLD-1', null, 4, '4.00'),
            );
            self::fail('A non-cold-chain receipt should have been rejected.');
        } catch (ColdChainRequired) {
            $this->assertSame(0, InventoryBatch::query()->count());
            $this->assertSame(0, StockMovement::query()->count());
        }

        $accepted = $service->receive(
            $pharmacy,
            $aggregate,
            $this->receipt(' Cold-1 ', ' Lot A ', 4, '4.00', coldChain: true),
        );
        $stateBeforeDuplicate = $accepted->fresh()->getAttributes();
        $movementCount = StockMovement::query()->count();
        $auditCount = InventoryAudit::query()->count();

        try {
            $service->receive(
                $pharmacy,
                $aggregate,
                $this->receipt('COLD-1', 'lot   a', 9, '5.00', coldChain: true),
            );
            self::fail('A normalized duplicate identity should have been rejected.');
        } catch (DuplicateBatchIdentity) {
            $this->assertSame($stateBeforeDuplicate, $accepted->fresh()->getAttributes());
            $this->assertSame($movementCount, StockMovement::query()->count());
            $this->assertSame($auditCount, InventoryAudit::query()->count());
            $this->assertSame(1, InventoryBatch::query()->count());
        }

        try {
            $service->receive(
                $otherPharmacy,
                $aggregate,
                $this->receipt('FOREIGN', null, 3, '3.00', coldChain: true),
            );
            self::fail('A foreign aggregate should have been rejected.');
        } catch (ForeignInventoryRecord) {
            $this->assertSame(1, InventoryBatch::query()->count());
            $this->assertSame(4, $aggregate->fresh()->stockQuantity);
        }
    }

    public function test_metadata_update_changes_only_safe_fields_and_preserves_quantity_and_movements(): void
    {
        Event::fake([InventoryUpdated::class]);
        CarbonImmutable::setTestNow('2025-06-10');
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $pharmacy = Pharmacy::factory()->create(['user_id' => $actor->id]);
        $aggregate = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $service = app(InventoryBatchService::class);
        $batch = $service->receive(
            $pharmacy,
            $aggregate,
            $this->receipt(
                'META-OLD',
                'LOT-OLD',
                10,
                '5.00',
                expiryDate: CarbonImmutable::parse('2025-12-31'),
                actorId: $actor->id,
            ),
        );
        $movementIds = $batch->movements()->pluck('id')->all();
        $quantityReceived = $batch->quantity_received;
        $currentQuantity = $batch->current_quantity;

        $updated = $service->updateMetadata(
            $pharmacy,
            $batch,
            new BatchMetadataData(
                batchNumber: ' META-NEW ',
                lotNumber: ' LOT-NEW ',
                price: '7.5',
                supplierName: ' Metadata Supplier ',
                expiryDate: CarbonImmutable::parse('2025-06-09'),
                coldChain: false,
                receivedDate: CarbonImmutable::parse('2025-06-01'),
                receivedReference: ' DELIVERY-7 ',
            ),
        );

        $this->assertSame('META-NEW', $updated->batch_number);
        $this->assertSame('LOT-NEW', $updated->lot_number);
        $this->assertSame('7.50', $updated->price);
        $this->assertSame('Metadata Supplier', $updated->supplier_name);
        $this->assertSame('2025-06-09', $updated->expiry_date->toDateString());
        $this->assertSame('2025-06-01', $updated->received_date->toDateString());
        $this->assertSame($quantityReceived, $updated->quantity_received);
        $this->assertSame($currentQuantity, $updated->current_quantity);
        $this->assertSame($movementIds, $updated->movements()->pluck('id')->all());
        $this->assertSame(0, $aggregate->fresh()->stockQuantity);
        $this->assertSame(2, InventoryAudit::query()->count());
    }

    public function test_duplicate_metadata_identity_is_rejected_without_mutating_the_batch(): void
    {
        Event::fake([InventoryUpdated::class]);
        CarbonImmutable::setTestNow('2025-07-01');
        $pharmacy = Pharmacy::factory()->create();
        $aggregate = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $service = app(InventoryBatchService::class);
        $first = $service->receive($pharmacy, $aggregate, $this->receipt('FIRST', 'LOT-1', 3, '3.00'));
        $service->receive($pharmacy, $aggregate, $this->receipt('SECOND', 'LOT-2', 4, '4.00'));
        $before = $first->fresh()->getAttributes();
        $movementCount = StockMovement::query()->count();
        $auditCount = InventoryAudit::query()->count();

        try {
            $service->updateMetadata(
                $pharmacy,
                $first,
                new BatchMetadataData(
                    batchNumber: ' second ',
                    lotNumber: 'lot-2',
                    price: '99.00',
                    receivedDate: CarbonImmutable::parse('2025-07-01'),
                ),
            );
            self::fail('A duplicate metadata identity should have been rejected.');
        } catch (DuplicateBatchIdentity) {
            $this->assertSame($before, $first->fresh()->getAttributes());
            $this->assertSame($movementCount, StockMovement::query()->count());
            $this->assertSame($auditCount, InventoryAudit::query()->count());
        }
    }

    public function test_quantity_correction_records_movement_and_audit_without_changing_received_quantity(): void
    {
        Event::fake([InventoryUpdated::class]);
        CarbonImmutable::setTestNow('2025-08-01');
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $pharmacy = Pharmacy::factory()->create(['user_id' => $actor->id]);
        $aggregate = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $service = app(InventoryBatchService::class);
        $batch = $service->receive(
            $pharmacy,
            $aggregate,
            $this->receipt('COUNT-1', null, 10, '12.00', actorId: $actor->id),
        );

        $result = $service->correctQuantity($pharmacy, $batch, 4, '  Count discrepancy  ');

        $corrected = $batch->fresh();
        $this->assertSame(10, $corrected->quantity_received);
        $this->assertSame(4, $corrected->current_quantity);
        $this->assertSame(4, $aggregate->fresh()->stockQuantity);
        $this->assertCount(1, $result->movements);
        $movement = $result->movements->sole();
        $this->assertSame('batch_correction', $movement->type);
        $this->assertSame(10, $movement->before_quantity);
        $this->assertSame(4, $movement->after_quantity);
        $this->assertSame(-6, $movement->quantity_delta);
        $this->assertSame('Count discrepancy', $movement->reason);
        $this->assertSame($actor->id, $movement->user_id);
        $this->assertNotNull($result->audit);
        $this->assertSame(10, $result->audit->before_quantity);
        $this->assertSame(4, $result->audit->after_quantity);
        $this->assertSame($result->operationId, $result->audit->operation_id);
    }

    public function test_invalid_correction_is_rejected_without_mutating_stock(): void
    {
        Event::fake([InventoryUpdated::class]);
        $pharmacy = Pharmacy::factory()->create();
        $aggregate = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $service = app(InventoryBatchService::class);
        $batch = $service->receive($pharmacy, $aggregate, $this->receipt('LIMIT-1', null, 5, '2.00'));
        $counts = [StockMovement::query()->count(), InventoryAudit::query()->count()];

        try {
            $service->correctQuantity($pharmacy, $batch, 6, 'Increase beyond receipt');
            self::fail('A correction above quantity received should have been rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame(5, $batch->fresh()->current_quantity);
            $this->assertSame(5, $aggregate->fresh()->stockQuantity);
            $this->assertSame($counts[0], StockMovement::query()->count());
            $this->assertSame($counts[1], InventoryAudit::query()->count());
        }

        try {
            $service->correctQuantity($pharmacy, $batch, 2, '   ');
            self::fail('A blank correction reason should have been rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame(5, $batch->fresh()->current_quantity);
        }
    }

    private function receipt(
        string $batchNumber,
        ?string $lotNumber,
        int $quantity,
        string $price,
        ?string $supplierName = null,
        ?string $reference = null,
        ?CarbonImmutable $expiryDate = null,
        bool $coldChain = false,
        ?int $actorId = null,
    ): BatchReceiptData {
        return new BatchReceiptData(
            batchNumber: $batchNumber,
            lotNumber: $lotNumber,
            quantityReceived: $quantity,
            price: $price,
            supplierName: $supplierName,
            expiryDate: $expiryDate,
            coldChain: $coldChain,
            receivedReference: $reference,
            createdBy: $actorId,
        );
    }
}
