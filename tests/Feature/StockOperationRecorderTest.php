<?php

namespace Tests\Feature;

use App\Domain\Inventory\Data\BatchQuantityChange;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\StockOperationRecorder;
use App\Events\InventoryUpdated;
use App\Models\InventoryAudit;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class StockOperationRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_correlated_movements_and_one_aggregate_audit(): void
    {
        Event::fake([InventoryUpdated::class]);
        $actor = User::factory()->create();
        $aggregate = InventoryItem::factory()->create([
            'stockQuantity' => 12,
            'price' => '10.00',
        ]);
        $first = $this->batch($aggregate, 'FIRST', 7);
        $second = $this->batch($aggregate, 'SECOND', 5);
        $context = new StockOperationContext(
            type: 'fefo_decrease',
            actorId: $actor->id,
            reason: 'Dispensed prescription',
            referenceType: 'controlled_substance_log',
            referenceId: 42,
            receivedReference: 'RX-100',
            operationId: 'operation-100',
        );

        $result = DB::transaction(function () use ($aggregate, $first, $second, $context) {
            $first->update(['current_quantity' => 0]);
            $second->update(['current_quantity' => 3]);
            $aggregate->update(['stockQuantity' => 3, 'price' => '11.50']);

            return app(StockOperationRecorder::class)->record(
                $aggregate,
                [
                    new BatchQuantityChange($first, 7, 0),
                    new BatchQuantityChange($second, 5, 3),
                ],
                beforeAvailableQuantity: 12,
                beforeRepresentativePrice: '10.00',
                context: $context,
            );
        });

        self::assertSame('operation-100', $result->operationId);
        self::assertCount(2, $result->movements);
        self::assertNotNull($result->audit);
        self::assertSame(3, $result->aggregate->stockQuantity);

        $movements = StockMovement::query()->orderBy('inventory_batch_id')->get();
        self::assertCount(2, $movements);
        self::assertSame([-7, -2], $movements->pluck('quantity_delta')->all());

        foreach ($movements as $movement) {
            self::assertSame('operation-100', $movement->operation_id);
            self::assertSame('fefo_decrease', $movement->type);
            self::assertSame('Dispensed prescription', $movement->reason);
            self::assertSame('controlled_substance_log', $movement->reference_type);
            self::assertSame('42', $movement->reference_id);
            self::assertSame('RX-100', $movement->received_reference);
            self::assertSame($actor->id, $movement->user_id);
        }

        $audit = InventoryAudit::query()->sole();
        self::assertSame('operation-100', $audit->operation_id);
        self::assertSame(12, $audit->before_quantity);
        self::assertSame(3, $audit->after_quantity);
        self::assertSame($actor->id, $audit->user_id);

        Event::assertDispatchedTimes(InventoryUpdated::class, 1);
        Event::assertDispatched(InventoryUpdated::class, function (InventoryUpdated $event) use ($aggregate): bool {
            return $event->pharmacyId === $aggregate->pharmacy_id
                && $event->medicineId === $aggregate->medicine_id
                && $event->stock === 3
                && $event->price === 11.5;
        });
    }

    public function test_price_only_change_broadcasts_without_creating_a_quantity_audit(): void
    {
        Event::fake([InventoryUpdated::class]);
        $aggregate = InventoryItem::factory()->create([
            'stockQuantity' => 4,
            'price' => '10.00',
        ]);

        $result = DB::transaction(function () use ($aggregate) {
            $aggregate->update(['price' => '12.25']);

            return app(StockOperationRecorder::class)->record(
                $aggregate,
                [],
                beforeAvailableQuantity: 4,
                beforeRepresentativePrice: '10.00',
                context: new StockOperationContext('metadata', operationId: 'price-only'),
            );
        });

        self::assertNull($result->audit);
        self::assertCount(0, $result->movements);
        self::assertSame(0, InventoryAudit::count());
        Event::assertDispatchedTimes(InventoryUpdated::class, 1);
    }

    public function test_rolled_back_operation_persists_no_ledger_records_or_broadcast(): void
    {
        Event::fake([InventoryUpdated::class]);
        $aggregate = InventoryItem::factory()->create([
            'stockQuantity' => 5,
            'price' => '10.00',
        ]);
        $batch = $this->batch($aggregate, 'ROLLBACK', 5);

        try {
            DB::transaction(function () use ($aggregate, $batch): void {
                $batch->update(['current_quantity' => 2]);
                $aggregate->update(['stockQuantity' => 2]);

                app(StockOperationRecorder::class)->record(
                    $aggregate,
                    [new BatchQuantityChange($batch, 5, 2)],
                    beforeAvailableQuantity: 5,
                    beforeRepresentativePrice: '10.00',
                    context: new StockOperationContext('batch_correction', operationId: 'rolled-back'),
                );

                throw new RuntimeException('Abort operation.');
            });
            self::fail('The transaction should have been rolled back.');
        } catch (RuntimeException $exception) {
            self::assertSame('Abort operation.', $exception->getMessage());
        }

        self::assertSame(0, StockMovement::count());
        self::assertSame(0, InventoryAudit::count());
        self::assertSame(5, $batch->fresh()->current_quantity);
        self::assertSame(5, $aggregate->fresh()->stockQuantity);
        Event::assertNotDispatched(InventoryUpdated::class);
    }

    private function batch(InventoryItem $aggregate, string $batchNumber, int $quantity): InventoryBatch
    {
        return InventoryBatch::factory()->create([
            'inventory_item_id' => $aggregate->id,
            'batch_number' => $batchNumber,
            'lot_number' => null,
            'identity_key' => "batch:{$batchNumber}|lot:",
            'quantity_received' => $quantity,
            'current_quantity' => $quantity,
            'price' => $aggregate->price,
        ]);
    }
}
