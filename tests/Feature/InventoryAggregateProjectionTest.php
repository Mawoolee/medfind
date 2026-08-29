<?php

namespace Tests\Feature;

use App\Domain\Inventory\AggregateSynchronizer;
use App\Domain\Inventory\BatchIdentity;
use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryAggregateProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_projects_batch_derived_values_across_an_expiry_date_boundary(): void
    {
        $aggregate = InventoryItem::factory()->create([
            'stockQuantity' => 999,
            'price' => '1.00',
            'expiry_date' => '2030-01-01',
            'par_level' => 2,
        ]);

        $this->batch($aggregate, 'EXPIRED', 7, '25.00', '2025-01-05', '2025-01-03');
        $this->batch($aggregate, 'DATED', 4, '20.00', '2025-01-10', '2025-01-01');
        $this->batch($aggregate, 'NO-EXPIRY', 3, '30.00', null, '2025-01-02');

        $query = app(InventoryAggregateQuery::class);
        $onExpiryDate = $query->withProjections(
            InventoryItem::query()->whereKey($aggregate),
            CarbonImmutable::parse('2025-01-10')
        )->firstOrFail();

        $this->assertSame(7, $onExpiryDate->available_stock);
        $this->assertSame(14, $onExpiryDate->physical_stock);
        $this->assertSame('30.00', $onExpiryDate->representative_price);
        $this->assertSame('2025-01-10', $onExpiryDate->nearest_valid_expiry->toDateString());

        app(AggregateSynchronizer::class)->synchronizeLocked($aggregate, CarbonImmutable::parse('2025-01-10'));
        $this->assertSame(7, $aggregate->fresh()->stockQuantity);

        $afterExpiry = $query->withProjections(
            InventoryItem::query()->whereKey($aggregate),
            CarbonImmutable::parse('2025-01-11')
        )->firstOrFail();

        $this->assertSame(3, $afterExpiry->available_stock);
        $this->assertSame(7, $afterExpiry->stockQuantity, 'The cached projection should remain unchanged before reconciliation.');
        $this->assertNull($afterExpiry->nearest_valid_expiry);
    }

    public function test_query_filters_use_batch_source_of_truth_instead_of_cached_aggregate_values(): void
    {
        $asOf = CarbonImmutable::parse('2025-04-10');
        $low = InventoryItem::factory()->create(['stockQuantity' => 500, 'par_level' => 5]);
        $available = InventoryItem::factory()->create(['stockQuantity' => 0, 'par_level' => 2]);
        $out = InventoryItem::factory()->create(['stockQuantity' => 500, 'par_level' => 5]);

        $this->batch($low, 'LOW', 4, '10.00', '2025-04-12', '2025-04-01');
        $this->batch($available, 'AVAILABLE', 8, '10.00', null, '2025-04-01');
        $this->batch($out, 'EXPIRED-ONLY', 9, '10.00', '2025-04-09', '2025-04-01');

        $query = app(InventoryAggregateQuery::class);

        $this->assertEqualsCanonicalizing(
            [$low->id, $available->id],
            $query->available(InventoryItem::query(), $asOf)->pluck('id')->all()
        );
        $this->assertSame([$low->id], $query->belowPar(InventoryItem::query(), $asOf)->pluck('id')->all());
        $this->assertSame([$out->id], $query->outOfStock(InventoryItem::query(), $asOf)->pluck('id')->all());
        $this->assertSame([$low->id], $query->expiringWithin(InventoryItem::query(), 2, $asOf)->pluck('id')->all());
        $this->assertSame([$out->id], $query->expiredPhysicalStock(InventoryItem::query(), $asOf)->pluck('id')->all());
    }

    public function test_batches_are_returned_in_deterministic_fefo_order_with_no_expiry_last(): void
    {
        $aggregate = InventoryItem::factory()->create();
        $later = $this->batch($aggregate, 'LATER', 1, '1.00', '2025-06-20', '2025-01-01');
        $sameExpiryLaterReceipt = $this->batch($aggregate, 'SAME-LATE', 1, '1.00', '2025-06-10', '2025-02-01');
        $sameExpiryEarlierReceipt = $this->batch($aggregate, 'SAME-EARLY', 1, '1.00', '2025-06-10', '2025-01-01');
        $noExpiry = $this->batch($aggregate, 'NO-EXPIRY', 1, '1.00', null, '2024-01-01');

        $ids = app(InventoryAggregateQuery::class)
            ->batchesInFefoOrder($aggregate)
            ->pluck('id')
            ->all();

        $this->assertSame([
            $sameExpiryEarlierReceipt->id,
            $sameExpiryLaterReceipt->id,
            $later->id,
            $noExpiry->id,
        ], $ids);
    }

    public function test_synchronizer_updates_quantity_status_price_and_nearest_expiry(): void
    {
        $aggregate = InventoryItem::factory()->create([
            'stockQuantity' => 99,
            'price' => '5.00',
            'status' => 'available',
            'expiry_date' => '2030-01-01',
            'par_level' => 10,
        ]);

        $this->batch($aggregate, 'EARLY', 4, '12.00', '2025-08-20', '2025-08-01');
        $this->batch($aggregate, 'LATEST', 3, '18.50', '2025-09-20', '2025-08-05');

        $result = app(AggregateSynchronizer::class)
            ->synchronizeLocked($aggregate, CarbonImmutable::parse('2025-08-10'));

        $this->assertSame(7, $result->stockQuantity);
        $this->assertSame('low_stock', $result->status);
        $this->assertSame('18.50', (string) $result->price);
        $this->assertSame('2025-08-20', $result->expiry_date->toDateString());
    }

    public function test_synchronizer_uses_latest_batch_price_fallback_and_retains_price_without_batches(): void
    {
        $expiredAggregate = InventoryItem::factory()->create(['stockQuantity' => 10, 'price' => '1.00']);
        $this->batch($expiredAggregate, 'OLDER', 3, '11.00', '2025-01-01', '2024-01-01');
        $this->batch($expiredAggregate, 'LATEST', 2, '22.00', '2025-01-02', '2024-02-01');

        $emptyAggregate = InventoryItem::factory()->create([
            'stockQuantity' => 10,
            'price' => '9.75',
            'expiry_date' => '2030-01-01',
        ]);

        $synchronizer = app(AggregateSynchronizer::class);
        $expired = $synchronizer->synchronizeLocked($expiredAggregate, CarbonImmutable::parse('2025-02-01'));
        $empty = $synchronizer->synchronizeLocked($emptyAggregate, CarbonImmutable::parse('2025-02-01'));

        $this->assertSame(0, $expired->stockQuantity);
        $this->assertSame('out_of_stock', $expired->status);
        $this->assertSame('22.00', (string) $expired->price);
        $this->assertNull($expired->expiry_date);

        $this->assertSame(0, $empty->stockQuantity);
        $this->assertSame('9.75', (string) $empty->price);
        $this->assertNull($empty->expiry_date);
    }

    public function test_chunk_reconciliation_reports_processed_and_changed_aggregates_and_is_idempotent(): void
    {
        $withStock = InventoryItem::factory()->create(['stockQuantity' => 90, 'price' => '1.00', 'par_level' => 2]);
        $this->batch($withStock, 'STOCK', 5, '15.00', null, '2025-01-01');
        InventoryItem::factory()->create(['stockQuantity' => 8, 'price' => '8.00', 'expiry_date' => '2030-01-01']);

        $synchronizer = app(AggregateSynchronizer::class);
        $first = $synchronizer->synchronizeChunk(1, CarbonImmutable::parse('2025-01-10'));
        $second = $synchronizer->synchronizeChunk(2, CarbonImmutable::parse('2025-01-10'));

        $this->assertSame(2, $first->processed);
        $this->assertSame(2, $first->updated);
        $this->assertSame(2, $second->processed);
        $this->assertSame(0, $second->updated);
    }

    public function test_invalid_reconciliation_and_expiry_window_sizes_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(AggregateSynchronizer::class)->synchronizeChunk(0, CarbonImmutable::parse('2025-01-01'));
    }

    private function batch(
        InventoryItem $aggregate,
        string $batchNumber,
        int $quantity,
        string $price,
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
            'price' => $price,
            'expiry_date' => $expiryDate,
            'received_date' => $receivedDate,
        ]);
    }
}
