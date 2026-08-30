<?php

namespace Tests\Feature;

use App\Domain\Inventory\AggregateSynchronizer;
use App\Domain\Inventory\BatchIdentity;
use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Eris\Generators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryPropertyGenerators;
use Tests\Support\InventoryReference;
use Tests\Support\PropertyTestCase;

final class Property8AggregateQuantityAndStatusProjectionTest extends PropertyTestCase
{
    use RefreshDatabase;

    protected function shouldSeed(): bool
    {
        return false;
    }

    /** **Validates: Requirements 4.2, 4.6, 4.7, 4.8** */
    public function test_aggregate_quantity_and_status_projection(): void
    {
        // Feature: pharmacy-medicine-batch-stock-management, Property 8: Aggregate quantity and status projection
        $aggregate = InventoryItem::factory()->create([
            'stockQuantity' => 999_999,
            'price' => '1.00',
            'status' => 'available',
            'par_level' => 0,
        ]);
        $synchronizer = app(AggregateSynchronizer::class);
        $aggregateQuery = app(InventoryAggregateQuery::class);
        $anchor = CarbonImmutable::parse('2030-06-15');
        $iterationsSinceGarbageCollection = 0;

        $this->forAll(
            Generators::choose(-3_650, 3_650),
            Generators::choose(0, 2_000),
            Generators::choose(0, 8),
            InventoryPropertyGenerators::batchVector(),
        )->then(function (
            int $asOfOffset,
            int $parLevel,
            int $batchCount,
            array $generatedBatches,
        ) use (
            $aggregate,
            $synchronizer,
            $aggregateQuery,
            $anchor,
            &$iterationsSinceGarbageCollection,
        ): void {
            $this->assertGeneratedProjection(
                $aggregate,
                $synchronizer,
                $aggregateQuery,
                $anchor->addDays($asOfOffset)->startOfDay(),
                $parLevel,
                array_slice($generatedBatches, 0, $batchCount),
            );

            if (++$iterationsSinceGarbageCollection === 10) {
                gc_collect_cycles();
                $iterationsSinceGarbageCollection = 0;
            }
        });
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $generatedBatches
     */
    private function assertGeneratedProjection(
        InventoryItem $aggregate,
        AggregateSynchronizer $synchronizer,
        InventoryAggregateQuery $aggregateQuery,
        CarbonImmutable $asOf,
        int $parLevel,
        array $generatedBatches,
    ): void {
        $synchronized = null;
        $projected = null;

        try {
            CarbonImmutable::setTestNow($asOf);
            DB::table('inventory_batches')->where('inventory_item_id', $aggregate->id)->delete();

            $referenceBatches = $this->insertGeneratedBatches($aggregate, $asOf, $generatedBatches);
            $timestamp = $asOf->setTime(12, 0)->toDateTimeString();

            DB::table('inventory_items')
                ->where('id', $aggregate->id)
                ->update([
                    'stockQuantity' => 999_999,
                    'status' => 'available',
                    'par_level' => $parLevel,
                    'updated_at' => $timestamp,
                ]);

            $batchStateBefore = $this->batchState($aggregate->id);
            $expiredBatchIdsBefore = $this->expiredBatchIds($aggregate->id, $asOf);
            $expectedAvailable = InventoryReference::availableStock($referenceBatches, $asOf);
            $expectedPhysical = InventoryReference::physicalStock($referenceBatches);
            $expectedStatus = $this->expectedStatus($expectedAvailable, $parLevel);

            $synchronized = $synchronizer->synchronizeLocked($aggregate->fresh(), $asOf);
            $projected = $aggregateQuery->withProjections(
                InventoryItem::query()->whereKey($aggregate->id),
                $asOf,
            )->firstOrFail();

            self::assertSame($expectedAvailable, (int) $synchronized->stockQuantity);
            self::assertSame($expectedStatus, $synchronized->status);
            self::assertSame($expectedAvailable, $projected->available_stock);
            self::assertSame($expectedPhysical, $projected->physical_stock);
            self::assertSame($expectedAvailable, (int) $projected->stockQuantity);
            self::assertSame($expectedStatus, $projected->status);
            self::assertSame(
                $batchStateBefore,
                $this->batchState($aggregate->id),
                'Synchronization must not mutate or delete authoritative batch rows.',
            );
            self::assertSame(
                $expiredBatchIdsBefore,
                $this->expiredBatchIds($aggregate->id, $asOf),
                'Expired batches must remain present after synchronization.',
            );
        } finally {
            CarbonImmutable::setTestNow();
            $synchronized?->unsetRelations();
            $projected?->unsetRelations();
            $aggregate->unsetRelations();
            DB::table('inventory_batches')->where('inventory_item_id', $aggregate->id)->delete();

            unset(
                $batchStateBefore,
                $expiredBatchIdsBefore,
                $expectedAvailable,
                $expectedPhysical,
                $expectedStatus,
                $projected,
                $referenceBatches,
                $synchronized,
            );
        }
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $generatedBatches
     * @return array<int, array{quantity: int, expiry: ?string}>
     */
    private function insertGeneratedBatches(
        InventoryItem $aggregate,
        CarbonImmutable $asOf,
        array $generatedBatches,
    ): array {
        $rows = [];
        $referenceBatches = [];
        $timestamp = $asOf->setTime(12, 0)->toDateTimeString();

        foreach (array_values($generatedBatches) as $index => $generatedBatch) {
            [$generatedQuantity, $expiryOffset, $priceCents] = $generatedBatch;
            $quantity = match ($index) {
                0, 2 => max(1, $generatedQuantity),
                1 => 0,
                default => $generatedQuantity,
            };
            $expiryDate = $this->expiryDate($asOf, $index, $expiryOffset);
            $batchNumber = "PROPERTY-8-BATCH-{$index}";

            $rows[] = [
                'inventory_item_id' => $aggregate->id,
                'legacy_source_inventory_item_id' => null,
                'batch_number' => $batchNumber,
                'lot_number' => null,
                'identity_key' => BatchIdentity::key($batchNumber, null),
                'quantity_received' => $quantity,
                'current_quantity' => $quantity,
                'price' => number_format($priceCents / 100, 2, '.', ''),
                'supplier_id' => null,
                'supplier_name' => null,
                'expiry_date' => $expiryDate,
                'cold_chain' => false,
                'received_date' => $asOf->subDays($index + 1)->toDateString(),
                'received_reference' => "PROPERTY-8-{$index}",
                'created_by' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $referenceBatches[] = [
                'quantity' => $quantity,
                'expiry' => $expiryDate,
            ];
        }

        if ($rows !== []) {
            DB::table('inventory_batches')->insert($rows);
        }

        return $referenceBatches;
    }

    private function expiryDate(CarbonImmutable $asOf, int $index, int $generatedOffset): ?string
    {
        return match ($index % 4) {
            0 => $asOf->subDays(abs($generatedOffset) + 1)->toDateString(),
            1 => $asOf->toDateString(),
            2 => $asOf->addDays(abs($generatedOffset) + 1)->toDateString(),
            default => null,
        };
    }

    private function expectedStatus(int $availableStock, int $parLevel): string
    {
        return match (true) {
            $availableStock === 0 => 'out_of_stock',
            $parLevel > 0 && $availableStock <= $parLevel => 'low_stock',
            default => 'available',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function batchState(int $aggregateId): array
    {
        return DB::table('inventory_batches')
            ->where('inventory_item_id', $aggregateId)
            ->orderBy('id')
            ->get([
                'id',
                'inventory_item_id',
                'identity_key',
                'current_quantity',
                'expiry_date',
            ])
            ->map(static fn (object $batch): array => (array) $batch)
            ->all();
    }

    /** @return array<int, int> */
    private function expiredBatchIds(int $aggregateId, CarbonImmutable $asOf): array
    {
        return DB::table('inventory_batches')
            ->where('inventory_item_id', $aggregateId)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $asOf->toDateString())
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
