<?php

namespace Tests\Feature;

use App\Domain\Inventory\BatchIdentity;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Exceptions\InsufficientAvailableStock;
use App\Domain\Inventory\FEFOAllocator;
use App\Models\InventoryAudit;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FEFOAllocatorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_decrease_excludes_expired_stock_and_spans_batches_in_deterministic_fefo_order(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 12:00:00');
        $pharmacy = Pharmacy::factory()->create();
        $aggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'stockQuantity' => 999,
            'price' => '10.00',
        ]);
        $expired = $this->batch($aggregate, 'EXPIRED', 20, '2025-06-09', '2025-01-01');
        $expiresToday = $this->batch($aggregate, 'TODAY', 2, '2025-06-10', '2025-05-04');
        $earlierReceipt = $this->batch($aggregate, 'EARLIER-RECEIPT', 2, '2025-06-20', '2025-05-01');
        $lowerIdTie = $this->batch($aggregate, 'LOWER-ID-TIE', 2, '2025-06-20', '2025-05-02');
        $higherIdTie = $this->batch($aggregate, 'HIGHER-ID-TIE', 2, '2025-06-20', '2025-05-02');
        $laterExpiry = $this->batch($aggregate, 'LATER', 3, '2025-07-01', '2025-01-01');
        $noExpiry = $this->batch($aggregate, 'NO-EXPIRY', 4, null, '2024-01-01');
        $context = new StockOperationContext(
            type: 'fefo_decrease',
            reason: 'Dispensed stock',
            operationId: 'fefo-ordered-allocation',
        );

        $result = app(FEFOAllocator::class)->decrease($pharmacy, $aggregate, 7, $context);

        self::assertSame(20, $expired->fresh()->current_quantity);
        self::assertSame(0, $expiresToday->fresh()->current_quantity);
        self::assertSame(0, $earlierReceipt->fresh()->current_quantity);
        self::assertSame(0, $lowerIdTie->fresh()->current_quantity);
        self::assertSame(1, $higherIdTie->fresh()->current_quantity);
        self::assertSame(3, $laterExpiry->fresh()->current_quantity);
        self::assertSame(4, $noExpiry->fresh()->current_quantity);
        self::assertSame(8, $result->aggregate->stockQuantity);
        self::assertSame(8, $aggregate->fresh()->stockQuantity);

        self::assertSame(
            [$expiresToday->id, $earlierReceipt->id, $lowerIdTie->id, $higherIdTie->id],
            $result->movements->pluck('inventory_batch_id')->all(),
        );
        self::assertSame([-2, -2, -2, -1], $result->movements->pluck('quantity_delta')->all());
        self::assertSame(4, StockMovement::query()->count());
        self::assertTrue(
            StockMovement::query()->get()->every(
                static fn (StockMovement $movement): bool => $movement->operation_id === 'fefo-ordered-allocation'
            )
        );
        self::assertNotNull($result->audit);
        self::assertSame(15, $result->audit->before_quantity);
        self::assertSame(8, $result->audit->after_quantity);
    }

    public function test_insufficient_available_stock_rolls_back_every_quantity_and_ledger_change(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 12:00:00');
        $pharmacy = Pharmacy::factory()->create();
        $aggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'stockQuantity' => 3,
            'price' => '10.00',
        ]);
        $available = $this->batch($aggregate, 'AVAILABLE', 3, '2025-06-20', '2025-05-01');
        $expired = $this->batch($aggregate, 'EXPIRED', 9, '2025-06-09', '2025-01-01');

        try {
            app(FEFOAllocator::class)->decrease(
                $pharmacy,
                $aggregate,
                4,
                new StockOperationContext('fefo_decrease', operationId: 'insufficient-fefo'),
            );
            self::fail('The decrease should have been rejected.');
        } catch (InsufficientAvailableStock $exception) {
            self::assertSame(4, $exception->requested);
            self::assertSame(3, $exception->available);
        }

        self::assertSame(3, $available->fresh()->current_quantity);
        self::assertSame(9, $expired->fresh()->current_quantity);
        self::assertSame(3, $aggregate->fresh()->stockQuantity);
        self::assertSame(0, StockMovement::query()->count());
        self::assertSame(0, InventoryAudit::query()->count());
    }

    public function test_specific_batch_decrease_never_falls_back_to_other_batches(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 12:00:00');
        $pharmacy = Pharmacy::factory()->create();
        $aggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'stockQuantity' => 15,
            'price' => '10.00',
        ]);
        $otherBatch = $this->batch($aggregate, 'OTHER', 10, '2025-06-15', '2025-05-01');
        $selectedBatch = $this->batch($aggregate, 'SELECTED', 5, '2025-07-01', '2025-05-02');

        $result = app(FEFOAllocator::class)->decreaseSpecificBatch(
            $pharmacy,
            $selectedBatch,
            3,
            new StockOperationContext('recall', operationId: 'specific-success'),
        );

        self::assertSame(10, $otherBatch->fresh()->current_quantity);
        self::assertSame(2, $selectedBatch->fresh()->current_quantity);
        self::assertSame(12, $result->aggregate->stockQuantity);
        self::assertCount(1, $result->movements);
        self::assertSame($selectedBatch->id, $result->movements->sole()->inventory_batch_id);
        self::assertSame(-3, $result->movements->sole()->quantity_delta);

        try {
            app(FEFOAllocator::class)->decreaseSpecificBatch(
                $pharmacy,
                $selectedBatch,
                3,
                new StockOperationContext('recall', operationId: 'specific-insufficient'),
            );
            self::fail('The selected batch decrease should have been rejected.');
        } catch (InsufficientAvailableStock $exception) {
            self::assertSame(3, $exception->requested);
            self::assertSame(2, $exception->available);
        }

        self::assertSame(10, $otherBatch->fresh()->current_quantity);
        self::assertSame(2, $selectedBatch->fresh()->current_quantity);
        self::assertSame(12, $aggregate->fresh()->stockQuantity);
        self::assertSame(1, StockMovement::query()->count());
        self::assertSame(1, InventoryAudit::query()->count());
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
