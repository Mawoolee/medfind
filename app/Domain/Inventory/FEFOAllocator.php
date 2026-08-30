<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Data\BatchQuantityChange;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Data\StockOperationResult;
use App\Domain\Inventory\Exceptions\ForeignInventoryRecord;
use App\Domain\Inventory\Exceptions\InsufficientAvailableStock;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FEFOAllocator
{
    public function __construct(
        private readonly AggregateSynchronizer $synchronizer,
        private readonly StockOperationRecorder $recorder,
    ) {}

    public function decrease(
        Pharmacy $pharmacy,
        InventoryItem $aggregate,
        int $quantity,
        StockOperationContext $context,
    ): StockOperationResult {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use ($pharmacy, $aggregate, $quantity, $context): StockOperationResult {
            $asOf = CarbonImmutable::now()->startOfDay();
            $lockedAggregate = $this->lockAggregate($pharmacy, $aggregate->getKey());
            $batches = InventoryBatch::query()
                ->where('inventory_item_id', $lockedAggregate->getKey())
                ->available($asOf)
                ->fefo()
                ->lockForUpdate()
                ->get();
            $availableQuantity = (int) $batches->sum(
                static fn (InventoryBatch $batch): int => (int) $batch->current_quantity
            );

            if ($quantity > $availableQuantity) {
                throw new InsufficientAvailableStock($quantity, $availableQuantity);
            }

            $before = $this->synchronizer->synchronizeLocked($lockedAggregate, $asOf);
            $beforeAvailable = (int) $before->stockQuantity;
            $beforePrice = (string) $before->price;
            $remaining = $quantity;
            $changes = [];

            foreach ($batches as $batch) {
                if ($remaining === 0) {
                    break;
                }

                $beforeQuantity = (int) $batch->current_quantity;
                $allocated = min($beforeQuantity, $remaining);
                $afterQuantity = $beforeQuantity - $allocated;

                $batch->current_quantity = $afterQuantity;
                $batch->save();
                $changes[] = new BatchQuantityChange($batch, $beforeQuantity, $afterQuantity);
                $remaining -= $allocated;
            }

            if ($remaining !== 0) {
                throw new InsufficientAvailableStock($quantity, $availableQuantity);
            }

            return $this->recordSynchronizedOperation(
                $before,
                $changes,
                $beforeAvailable,
                $beforePrice,
                $context,
                $asOf,
            );
        });
    }

    public function decreaseSpecificBatch(
        Pharmacy $pharmacy,
        InventoryBatch $batch,
        int $quantity,
        StockOperationContext $context,
    ): StockOperationResult {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use ($pharmacy, $batch, $quantity, $context): StockOperationResult {
            $asOf = CarbonImmutable::now()->startOfDay();
            $lockedAggregate = $this->lockAggregateForBatch($pharmacy, $batch->getKey());
            $lockedBatch = InventoryBatch::query()
                ->whereKey($batch->getKey())
                ->where('inventory_item_id', $lockedAggregate->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedBatch === null) {
                throw new ForeignInventoryRecord;
            }

            $beforeQuantity = (int) $lockedBatch->current_quantity;

            if ($quantity > $beforeQuantity) {
                throw new InsufficientAvailableStock($quantity, $beforeQuantity);
            }

            $before = $this->synchronizer->synchronizeLocked($lockedAggregate, $asOf);
            $beforeAvailable = (int) $before->stockQuantity;
            $beforePrice = (string) $before->price;
            $afterQuantity = $beforeQuantity - $quantity;

            $lockedBatch->current_quantity = $afterQuantity;
            $lockedBatch->save();

            return $this->recordSynchronizedOperation(
                $before,
                [new BatchQuantityChange($lockedBatch, $beforeQuantity, $afterQuantity)],
                $beforeAvailable,
                $beforePrice,
                $context,
                $asOf,
            );
        });
    }

    /**
     * @param  list<BatchQuantityChange>  $changes
     */
    private function recordSynchronizedOperation(
        InventoryItem $aggregate,
        array $changes,
        int $beforeAvailable,
        string $beforePrice,
        StockOperationContext $context,
        CarbonImmutable $asOf,
    ): StockOperationResult {
        $synchronized = $this->synchronizer->synchronizeLocked($aggregate, $asOf);

        return $this->recorder->record(
            $synchronized,
            $changes,
            $beforeAvailable,
            $beforePrice,
            $context,
        );
    }

    private function lockAggregate(Pharmacy $pharmacy, int|string|null $aggregateId): InventoryItem
    {
        if ($pharmacy->getKey() === null || $aggregateId === null) {
            throw new ForeignInventoryRecord;
        }

        $aggregate = InventoryItem::query()
            ->whereKey($aggregateId)
            ->where('pharmacy_id', $pharmacy->getKey())
            ->lockForUpdate()
            ->first();

        if ($aggregate === null) {
            throw new ForeignInventoryRecord;
        }

        return $aggregate;
    }

    private function lockAggregateForBatch(Pharmacy $pharmacy, int|string|null $batchId): InventoryItem
    {
        if ($pharmacy->getKey() === null || $batchId === null) {
            throw new ForeignInventoryRecord;
        }

        $aggregate = InventoryItem::query()
            ->where('pharmacy_id', $pharmacy->getKey())
            ->whereHas('batches', static function (Builder $batches) use ($batchId): void {
                $batches->whereKey($batchId);
            })
            ->lockForUpdate()
            ->first();

        if ($aggregate === null) {
            throw new ForeignInventoryRecord;
        }

        return $aggregate;
    }

    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('The decrease quantity must be at least one.');
        }
    }
}
