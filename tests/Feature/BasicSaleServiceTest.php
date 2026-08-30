<?php

namespace Tests\Feature;

use App\Domain\Inventory\BasicSaleService;
use App\Domain\Inventory\BatchIdentity;
use App\Domain\Inventory\Exceptions\SaleLineInsufficientStock;
use App\Models\InventoryAudit;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class BasicSaleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_multi_item_sale_uses_fefo_excludes_expired_stock_and_correlates_the_ledger(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 14:30:00');
        [$pharmacy] = $this->pharmacyWithOwner();
        $staff = User::factory()->create([
            'role' => 'pharmacy_operator',
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Jamie Pharmacist',
        ]);
        $firstAggregate = $this->aggregate($pharmacy, 'Amoxicillin', 10);
        $secondAggregate = $this->aggregate($pharmacy, 'Cetirizine', 6);
        $expired = $this->batch($firstAggregate, 'A-EXPIRED', 20, '2025-06-09', '2025-01-01');
        $firstExpiry = $this->batch($firstAggregate, 'A-FIRST', 3, '2025-06-15', '2025-05-03');
        $laterExpiry = $this->batch($firstAggregate, 'A-LATER', 4, '2025-07-01', '2025-05-01');
        $noExpiry = $this->batch($firstAggregate, 'A-NONE', 3, null, '2025-01-01');
        $secondBatch = $this->batch($secondAggregate, 'B-ONLY', 6, '2025-08-01', '2025-05-01');

        $result = app(BasicSaleService::class)->record(
            $pharmacy,
            $staff,
            [
                ['inventory_item_id' => $firstAggregate->id, 'quantity' => 5],
                ['inventory_item_id' => $secondAggregate->id, 'quantity' => 2],
            ],
            'Counter sale',
        );

        self::assertMatchesRegularExpression('/^SALE-20250610-143000-[0-9A-Z]{26}$/', $result->saleReference);
        self::assertCount(2, $result->operations);
        self::assertSame(5, $firstAggregate->fresh()->stockQuantity);
        self::assertSame(4, $secondAggregate->fresh()->stockQuantity);
        self::assertSame(20, $expired->fresh()->current_quantity);
        self::assertSame(0, $firstExpiry->fresh()->current_quantity);
        self::assertSame(2, $laterExpiry->fresh()->current_quantity);
        self::assertSame(3, $noExpiry->fresh()->current_quantity);
        self::assertSame(4, $secondBatch->fresh()->current_quantity);

        $movements = StockMovement::query()->orderBy('id')->get();
        self::assertCount(3, $movements);
        self::assertSame(
            [$firstExpiry->id, $laterExpiry->id, $secondBatch->id],
            $movements->pluck('inventory_batch_id')->all(),
        );
        self::assertSame([-3, -2, -2], $movements->pluck('quantity_delta')->all());
        self::assertTrue($movements->every(fn (StockMovement $movement): bool => $movement->operation_id === $result->operationId
            && $movement->received_reference === $result->saleReference
            && $movement->reference_type === 'sale'
            && $movement->reference_id === $result->saleReference
            && $movement->user_id === $staff->id
            && str_contains((string) $movement->reason, $result->saleReference)
            && str_contains((string) $movement->reason, 'Counter sale')
            && $movement->created_at?->toDateTimeString() === '2025-06-10 14:30:00'
        ));

        $audits = InventoryAudit::query()->orderBy('id')->get();
        self::assertCount(2, $audits);
        self::assertSame([[10, 5], [6, 4]], $audits->map(
            static fn (InventoryAudit $audit): array => [$audit->before_quantity, $audit->after_quantity]
        )->all());
        self::assertTrue($audits->every(fn (InventoryAudit $audit): bool => $audit->operation_id === $result->operationId
            && $audit->user_id === $staff->id
            && str_contains((string) $audit->notes, $result->saleReference)
            && $audit->created_at?->toDateTimeString() === '2025-06-10 14:30:00'
        ));
    }

    public function test_later_insufficient_line_rolls_back_every_earlier_sale_change(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 15:00:00');
        [$pharmacy, $owner] = $this->pharmacyWithOwner();
        $firstAggregate = $this->aggregate($pharmacy, 'Ibuprofen', 4);
        $secondAggregate = $this->aggregate($pharmacy, 'Loperamide', 1);
        $firstBatch = $this->batch($firstAggregate, 'FIRST', 4, '2025-07-01', '2025-05-01');
        $secondBatch = $this->batch($secondAggregate, 'SECOND', 1, '2025-07-01', '2025-05-01');

        try {
            app(BasicSaleService::class)->record($pharmacy, $owner, [
                ['inventory_item_id' => $firstAggregate->id, 'quantity' => 3],
                ['inventory_item_id' => $secondAggregate->id, 'quantity' => 2],
            ]);
            self::fail('The insufficient second line should reject the complete sale.');
        } catch (SaleLineInsufficientStock $exception) {
            self::assertSame(1, $exception->lineIndex);
            self::assertSame(2, $exception->requested);
            self::assertSame(1, $exception->available);
            self::assertStringContainsString('Loperamide', $exception->getMessage());
        }

        self::assertSame(4, $firstBatch->fresh()->current_quantity);
        self::assertSame(1, $secondBatch->fresh()->current_quantity);
        self::assertSame(4, $firstAggregate->fresh()->stockQuantity);
        self::assertSame(1, $secondAggregate->fresh()->stockQuantity);
        self::assertSame(0, StockMovement::query()->count());
        self::assertSame(0, InventoryAudit::query()->count());
    }

    public function test_service_rejects_duplicate_aggregate_lines_before_mutating_stock(): void
    {
        [$pharmacy, $owner] = $this->pharmacyWithOwner();
        $aggregate = $this->aggregate($pharmacy, 'Metformin', 5);
        $batch = $this->batch($aggregate, 'DUPLICATE', 5, '2025-07-01', '2025-05-01');

        try {
            app(BasicSaleService::class)->record($pharmacy, $owner, [
                ['inventory_item_id' => $aggregate->id, 'quantity' => 1],
                ['inventory_item_id' => $aggregate->id, 'quantity' => 2],
            ]);
            self::fail('Duplicate aggregate lines should be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('duplicates an earlier medicine', $exception->getMessage());
        }

        self::assertSame(5, $batch->fresh()->current_quantity);
        self::assertSame(5, $aggregate->fresh()->stockQuantity);
        self::assertDatabaseCount('stock_movements', 0);
        self::assertDatabaseCount('inventory_audits', 0);
    }

    /** @return array{0: Pharmacy, 1: User} */
    private function pharmacyWithOwner(): array
    {
        $owner = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($owner)->create();
        $owner->update(['pharmacy_id' => $pharmacy->id]);

        return [$pharmacy, $owner];
    }

    private function aggregate(Pharmacy $pharmacy, string $medicineName, int $available): InventoryItem
    {
        return InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => Medicine::factory()->create(['medicine_name' => $medicineName])->id,
            'stockQuantity' => $available,
            'price' => '10.00',
        ]);
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
