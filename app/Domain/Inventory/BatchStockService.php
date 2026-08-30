<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Data\BatchQuantityChange;
use App\Domain\Inventory\Data\BatchReceiptData;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Data\StockOperationResult;
use App\Domain\Inventory\Exceptions\ColdChainRequired;
use App\Domain\Inventory\Exceptions\DuplicateBatchIdentity;
use App\Domain\Inventory\Exceptions\InsufficientAvailableStock;
use App\Domain\Inventory\Exceptions\UntraceableStockIncrease;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BatchStockService
{
    public function __construct(
        private readonly AggregateSynchronizer $synchronizer,
        private readonly StockOperationRecorder $recorder,
    ) {}

    public function receive(
        InventoryItem $aggregate,
        BatchReceiptData $receipt,
        ?StockOperationContext $context = null,
    ): StockOperationResult {
        if ($receipt->quantityReceived < 1) {
            throw new InvalidArgumentException('Quantity received must be at least one.');
        }

        if ((float) $receipt->price < 0) {
            throw new InvalidArgumentException('Batch price cannot be negative.');
        }

        return DB::transaction(function () use ($aggregate, $receipt, $context): StockOperationResult {
            $asOf = CarbonImmutable::now()->startOfDay();
            $locked = $this->lockAndSynchronize($aggregate, $asOf);

            if ($locked->medicine?->cold_chain_required && ! $receipt->coldChain) {
                throw new ColdChainRequired;
            }

            $identityKey = BatchIdentity::key($receipt->batchNumber, $receipt->lotNumber);
            $normalizedBatch = BatchIdentity::normalize($receipt->batchNumber);
            $normalizedLot = BatchIdentity::normalize($receipt->lotNumber ?? '');
            $hasDigestDuplicate = $locked->batches()
                ->where('identity_key', $identityKey)
                ->exists();
            $hasTransitionalDuplicate = $locked->batches()
                ->select(['identity_key', 'batch_number', 'lot_number'])
                ->where('identity_key', 'not like', BatchIdentity::DIGEST_PREFIX.'%')
                ->lockForUpdate()
                ->get()
                ->contains(fn (InventoryBatch $batch): bool => BatchIdentity::normalize($batch->batch_number) === $normalizedBatch
                    && BatchIdentity::normalize($batch->lot_number ?? '') === $normalizedLot
                );

            if ($hasDigestDuplicate || $hasTransitionalDuplicate) {
                throw new DuplicateBatchIdentity($receipt->batchNumber, $receipt->lotNumber);
            }

            $supplierName = $receipt->supplierName;
            if ($supplierName === null && $receipt->supplierId !== null) {
                $supplierName = Supplier::query()->find($receipt->supplierId)?->name;
            }

            $beforeAvailable = (int) $locked->stockQuantity;
            $beforePrice = (string) $locked->price;
            $receivedDate = $receipt->receivedDate ?? $asOf;

            $batch = InventoryBatch::query()->create([
                'inventory_item_id' => $locked->getKey(),
                'batch_number' => trim($receipt->batchNumber),
                'lot_number' => $this->nullableTrimmed($receipt->lotNumber),
                'identity_key' => $identityKey,
                'quantity_received' => $receipt->quantityReceived,
                'current_quantity' => $receipt->quantityReceived,
                'price' => number_format((float) $receipt->price, 2, '.', ''),
                'supplier_id' => $receipt->supplierId,
                'supplier_name' => $supplierName,
                'expiry_date' => $receipt->expiryDate?->toDateString(),
                'cold_chain' => $receipt->coldChain,
                'received_date' => $receivedDate->toDateString(),
                'received_reference' => $receipt->receivedReference,
                'created_by' => $receipt->createdBy,
            ]);

            $synchronized = $this->synchronizer->synchronizeLocked($locked, $asOf);
            $operation = $context ?? new StockOperationContext(
                type: 'receipt',
                actorId: $receipt->createdBy,
                reason: 'Stock received',
                receivedReference: $receipt->receivedReference,
            );

            return $this->recorder->record(
                $synchronized,
                [new BatchQuantityChange($batch, 0, $receipt->quantityReceived)],
                $beforeAvailable,
                $beforePrice,
                $operation,
            );
        });
    }

    public function decreaseFefo(
        InventoryItem $aggregate,
        int $quantity,
        StockOperationContext $context,
    ): StockOperationResult {
        if ($quantity < 1) {
            throw new InvalidArgumentException('The decrease quantity must be at least one.');
        }

        return DB::transaction(function () use ($aggregate, $quantity, $context): StockOperationResult {
            $asOf = CarbonImmutable::now()->startOfDay();
            $locked = $this->lockAndSynchronize($aggregate, $asOf);

            return $this->decreaseLocked($locked, $quantity, $context, $asOf);
        });
    }

    public function setAvailableQuantity(
        InventoryItem $aggregate,
        int $targetQuantity,
        StockOperationContext $context,
    ): StockOperationResult {
        if ($targetQuantity < 0) {
            throw new InvalidArgumentException('Target quantity cannot be negative.');
        }

        return DB::transaction(function () use ($aggregate, $targetQuantity, $context): StockOperationResult {
            $asOf = CarbonImmutable::now()->startOfDay();
            $locked = $this->lockAndSynchronize($aggregate, $asOf);
            $currentQuantity = (int) $locked->stockQuantity;

            if ($targetQuantity > $currentQuantity) {
                throw new UntraceableStockIncrease;
            }

            if ($targetQuantity === $currentQuantity) {
                return $this->recorder->record(
                    $locked,
                    [],
                    $currentQuantity,
                    (string) $locked->price,
                    $context,
                );
            }

            return $this->decreaseLocked(
                $locked,
                $currentQuantity - $targetQuantity,
                $context,
                $asOf,
            );
        });
    }

    private function decreaseLocked(
        InventoryItem $aggregate,
        int $quantity,
        StockOperationContext $context,
        CarbonImmutable $asOf,
    ): StockOperationResult {
        $beforeAvailable = (int) $aggregate->stockQuantity;
        $beforePrice = (string) $aggregate->price;

        if ($quantity > $beforeAvailable) {
            throw new InsufficientAvailableStock($quantity, $beforeAvailable);
        }

        $remaining = $quantity;
        $changes = [];
        $batches = InventoryBatch::query()
            ->where('inventory_item_id', $aggregate->getKey())
            ->available($asOf)
            ->fefo()
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining === 0) {
                break;
            }

            $before = (int) $batch->current_quantity;
            $deducted = min($before, $remaining);
            $after = $before - $deducted;

            $batch->current_quantity = $after;
            $batch->save();
            $changes[] = new BatchQuantityChange($batch, $before, $after);
            $remaining -= $deducted;
        }

        if ($remaining !== 0) {
            throw new InsufficientAvailableStock($quantity, $beforeAvailable - $remaining);
        }

        $synchronized = $this->synchronizer->synchronizeLocked($aggregate, $asOf);

        return $this->recorder->record(
            $synchronized,
            $changes,
            $beforeAvailable,
            $beforePrice,
            $context,
        );
    }

    private function lockAndSynchronize(InventoryItem $aggregate, CarbonImmutable $asOf): InventoryItem
    {
        $locked = InventoryItem::query()
            ->with('medicine')
            ->whereKey($aggregate->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $this->synchronizer
            ->synchronizeLocked($locked, $asOf)
            ->loadMissing('medicine');
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
