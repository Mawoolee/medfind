<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Data\BatchQuantityChange;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Data\StockOperationResult;
use App\Events\InventoryUpdated;
use App\Models\InventoryAudit;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

final class StockOperationRecorder
{
    /**
     * Record all ledger entries for an already-applied stock operation.
     *
     * The caller must invoke this after changing batches and synchronizing the
     * aggregate, while the encompassing stock transaction is still active.
     *
     * @param  iterable<BatchQuantityChange>  $changes
     */
    public function record(
        InventoryItem $aggregate,
        iterable $changes,
        int $beforeAvailableQuantity,
        int|float|string $beforeRepresentativePrice,
        StockOperationContext $context,
    ): StockOperationResult {
        $this->assertActiveTransaction($aggregate);
        $aggregate->refresh();

        $movements = new Collection;
        $recordedBatchIds = [];

        foreach ($changes as $change) {
            if (! $change instanceof BatchQuantityChange) {
                throw new InvalidArgumentException('Stock operation changes must be BatchQuantityChange instances.');
            }

            if (! $change->changed()) {
                continue;
            }

            $batchId = (int) $change->batch->getKey();

            if (isset($recordedBatchIds[$batchId])) {
                throw new InvalidArgumentException('A stock operation may record each batch only once.');
            }

            $recordedBatchIds[$batchId] = true;
            $movements->push($this->recordMovement($aggregate, $change, $context));
        }

        $afterAvailableQuantity = (int) $aggregate->stockQuantity;
        $audit = $this->recordAudit(
            $aggregate,
            $beforeAvailableQuantity,
            $afterAvailableQuantity,
            $context,
        );

        $this->scheduleBroadcast(
            $aggregate,
            $beforeAvailableQuantity,
            $beforeRepresentativePrice,
        );

        return new StockOperationResult(
            $context->operationId,
            $aggregate,
            $movements,
            $audit,
        );
    }

    /**
     * Descriptive alias for callers that prefer the full operation name.
     *
     * @param  iterable<BatchQuantityChange>  $changes
     */
    public function recordOperation(
        InventoryItem $aggregate,
        iterable $changes,
        int $beforeAvailableQuantity,
        int|float|string $beforeRepresentativePrice,
        StockOperationContext $context,
    ): StockOperationResult {
        return $this->record(
            $aggregate,
            $changes,
            $beforeAvailableQuantity,
            $beforeRepresentativePrice,
            $context,
        );
    }

    public function recordMovement(
        InventoryItem $aggregate,
        BatchQuantityChange $change,
        StockOperationContext $context,
    ): StockMovement {
        $this->assertActiveTransaction($aggregate);
        $batch = $change->batch;

        if (! $aggregate->exists || ! $batch->exists || (int) $batch->inventory_item_id !== (int) $aggregate->getKey()) {
            throw new InvalidArgumentException('The changed batch must belong to the recorded inventory aggregate.');
        }

        if ((int) $batch->current_quantity !== $change->afterQuantity) {
            throw new InvalidArgumentException('The changed batch must be persisted before its movement is recorded.');
        }

        return StockMovement::query()->create([
            'operation_id' => $context->operationId,
            'inventory_item_id' => $aggregate->getKey(),
            'inventory_batch_id' => $batch->getKey(),
            'type' => $context->type,
            'before_quantity' => $change->beforeQuantity,
            'after_quantity' => $change->afterQuantity,
            'quantity_delta' => $change->delta(),
            'reason' => $context->reason,
            'reference_type' => $context->referenceType,
            'reference_id' => $context->referenceId === null ? null : (string) $context->referenceId,
            'received_reference' => $context->receivedReference,
            'user_id' => $context->actorId,
        ]);
    }

    public function recordAudit(
        InventoryItem $aggregate,
        int $beforeAvailableQuantity,
        int $afterAvailableQuantity,
        StockOperationContext $context,
    ): ?InventoryAudit {
        $this->assertActiveTransaction($aggregate);

        if ($beforeAvailableQuantity === $afterAvailableQuantity) {
            return null;
        }

        return InventoryAudit::query()->create([
            'inventory_item_id' => $aggregate->getKey(),
            'user_id' => $context->actorId,
            'before_quantity' => $beforeAvailableQuantity,
            'after_quantity' => $afterAvailableQuantity,
            'notes' => $context->reason ?? $context->type,
            'operation_id' => $context->operationId,
        ]);
    }

    public function scheduleBroadcast(
        InventoryItem $aggregate,
        int $beforeAvailableQuantity,
        int|float|string $beforeRepresentativePrice,
    ): void {
        $this->assertActiveTransaction($aggregate);

        $afterAvailableQuantity = (int) $aggregate->stockQuantity;
        $afterRepresentativePrice = $this->normalizedPrice($aggregate->price);

        if (
            $beforeAvailableQuantity === $afterAvailableQuantity
            && $this->normalizedPrice($beforeRepresentativePrice) === $afterRepresentativePrice
        ) {
            return;
        }

        $aggregate->loadMissing('medicine');
        $eventPayload = [
            (int) $aggregate->pharmacy_id,
            $aggregate->medicine_id === null ? null : (int) $aggregate->medicine_id,
            $aggregate->medicine?->medicine_name,
            $afterAvailableQuantity,
            (float) $afterRepresentativePrice,
            (bool) ($aggregate->medicine?->requiresPrescription ?? false),
        ];

        $aggregate->getConnection()->afterCommit(
            static fn () => InventoryUpdated::dispatch(...$eventPayload)
        );
    }

    private function assertActiveTransaction(InventoryItem $aggregate): void
    {
        if ($aggregate->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Stock operations must be recorded inside the stock transaction.');
        }
    }

    private function normalizedPrice(int|float|string|null $price): string
    {
        return number_format(max(0, (float) $price), 2, '.', '');
    }
}
