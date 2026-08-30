<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Data\BatchMetadataData;
use App\Domain\Inventory\Data\BatchQuantityChange;
use App\Domain\Inventory\Data\BatchReceiptData;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Data\StockOperationResult;
use App\Domain\Inventory\Exceptions\ColdChainRequired;
use App\Domain\Inventory\Exceptions\DuplicateBatchIdentity;
use App\Domain\Inventory\Exceptions\ForeignInventoryRecord;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventoryBatchService
{
    public function __construct(
        private readonly AggregateSynchronizer $synchronizer,
        private readonly StockOperationRecorder $recorder,
    ) {}

    public function receive(
        Pharmacy $pharmacy,
        InventoryItem $aggregate,
        BatchReceiptData $data,
    ): InventoryBatch {
        $batchNumber = $this->requiredText($data->batchNumber, 'Batch number', 255);
        $lotNumber = $this->optionalText($data->lotNumber, 'Lot number', 255);
        $price = $this->price($data->price);
        $supplierName = $this->optionalText($data->supplierName, 'Supplier name', 255);
        $receivedReference = $this->optionalText($data->receivedReference, 'Received reference', 255);

        if ($data->quantityReceived < 1) {
            throw new InvalidArgumentException('Quantity received must be at least one.');
        }

        try {
            return DB::transaction(function () use (
                $pharmacy,
                $aggregate,
                $data,
                $batchNumber,
                $lotNumber,
                $price,
                $supplierName,
                $receivedReference,
            ): InventoryBatch {
                $asOf = CarbonImmutable::now()->startOfDay();
                $lockedAggregate = $this->lockAggregate($pharmacy, $aggregate);
                $lockedAggregate = $this->synchronizer->synchronizeLocked($lockedAggregate, $asOf);

                $this->assertColdChainAllowed($lockedAggregate, $data->coldChain);

                $identityKey = BatchIdentity::key($batchNumber, $lotNumber);
                $this->assertUniqueIdentity($lockedAggregate, $identityKey, $batchNumber, $lotNumber);

                [$supplierId, $supplierSnapshot] = $this->resolveSupplier($supplierName, $data->supplierId);
                $beforeAvailable = (int) $lockedAggregate->stockQuantity;
                $beforePrice = (string) $lockedAggregate->price;
                $receivedDate = ($data->receivedDate ?? $asOf)->startOfDay();

                $batch = InventoryBatch::query()->create([
                    'inventory_item_id' => $lockedAggregate->getKey(),
                    'batch_number' => $batchNumber,
                    'lot_number' => $lotNumber,
                    'identity_key' => $identityKey,
                    'quantity_received' => $data->quantityReceived,
                    'current_quantity' => $data->quantityReceived,
                    'price' => $price,
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplierSnapshot,
                    'expiry_date' => $data->expiryDate?->toDateString(),
                    'cold_chain' => $data->coldChain,
                    'received_date' => $receivedDate->toDateString(),
                    'received_reference' => $receivedReference,
                    'created_by' => $data->createdBy,
                ]);

                $synchronized = $this->synchronizer->synchronizeLocked($lockedAggregate, $asOf);
                $this->recorder->record(
                    $synchronized,
                    [new BatchQuantityChange($batch, 0, $data->quantityReceived)],
                    $beforeAvailable,
                    $beforePrice,
                    new StockOperationContext(
                        type: 'receipt',
                        actorId: $data->createdBy,
                        reason: 'Stock received',
                        receivedReference: $receivedReference,
                    ),
                );

                return $batch->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateBatchIdentity($batchNumber, $lotNumber);
        }
    }

    public function updateMetadata(
        Pharmacy $pharmacy,
        InventoryBatch $batch,
        BatchMetadataData $data,
    ): InventoryBatch {
        $batchNumber = $this->requiredText($data->batchNumber, 'Batch number', 255);
        $lotNumber = $this->optionalText($data->lotNumber, 'Lot number', 255);
        $price = $this->price($data->price);
        $supplierName = $this->optionalText($data->supplierName, 'Supplier name', 255);
        $receivedReference = $this->optionalText($data->receivedReference, 'Received reference', 255);

        try {
            return DB::transaction(function () use (
                $pharmacy,
                $batch,
                $data,
                $batchNumber,
                $lotNumber,
                $price,
                $supplierName,
                $receivedReference,
            ): InventoryBatch {
                $asOf = CarbonImmutable::now()->startOfDay();
                [$lockedAggregate, $lockedBatch] = $this->lockBatch($pharmacy, $batch);
                $lockedAggregate = $this->synchronizer->synchronizeLocked($lockedAggregate, $asOf);

                $this->assertColdChainAllowed($lockedAggregate, $data->coldChain);

                $identityKey = BatchIdentity::key($batchNumber, $lotNumber);
                $this->assertUniqueIdentity(
                    $lockedAggregate,
                    $identityKey,
                    $batchNumber,
                    $lotNumber,
                    $lockedBatch,
                );

                [$supplierId, $supplierSnapshot] = $this->resolveSupplier($supplierName, $data->supplierId);
                $beforeAvailable = (int) $lockedAggregate->stockQuantity;
                $beforePrice = (string) $lockedAggregate->price;

                $lockedBatch->fill([
                    'batch_number' => $batchNumber,
                    'lot_number' => $lotNumber,
                    'identity_key' => $identityKey,
                    'price' => $price,
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplierSnapshot,
                    'expiry_date' => $data->expiryDate?->toDateString(),
                    'cold_chain' => $data->coldChain,
                    'received_date' => $data->receivedDate?->toDateString()
                        ?? $lockedBatch->received_date->toDateString(),
                    'received_reference' => $receivedReference,
                ]);
                $lockedBatch->save();

                $synchronized = $this->synchronizer->synchronizeLocked($lockedAggregate, $asOf);
                $this->recorder->record(
                    $synchronized,
                    [],
                    $beforeAvailable,
                    $beforePrice,
                    new StockOperationContext(
                        type: 'batch_metadata',
                        actorId: $this->actorId(),
                        reason: 'Batch metadata updated',
                        receivedReference: $receivedReference,
                    ),
                );

                return $lockedBatch->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateBatchIdentity($batchNumber, $lotNumber);
        }
    }

    public function correctQuantity(
        Pharmacy $pharmacy,
        InventoryBatch $batch,
        int $target,
        string $reason,
    ): StockOperationResult {
        if ($target < 0) {
            throw new InvalidArgumentException('Target quantity cannot be negative.');
        }

        $reason = $this->requiredText($reason, 'Correction reason', 1000);

        return DB::transaction(function () use ($pharmacy, $batch, $target, $reason): StockOperationResult {
            $asOf = CarbonImmutable::now()->startOfDay();
            [$lockedAggregate, $lockedBatch] = $this->lockBatch($pharmacy, $batch);
            $lockedAggregate = $this->synchronizer->synchronizeLocked($lockedAggregate, $asOf);

            if ($target > (int) $lockedBatch->quantity_received) {
                throw new InvalidArgumentException(
                    'A batch correction cannot exceed the quantity originally received; create a traceable adjustment batch instead.'
                );
            }

            $beforeAvailable = (int) $lockedAggregate->stockQuantity;
            $beforePrice = (string) $lockedAggregate->price;
            $beforeQuantity = (int) $lockedBatch->current_quantity;

            if ($target !== $beforeQuantity) {
                $lockedBatch->current_quantity = $target;
                $lockedBatch->save();
            }

            $synchronized = $this->synchronizer->synchronizeLocked($lockedAggregate, $asOf);

            return $this->recorder->record(
                $synchronized,
                [new BatchQuantityChange($lockedBatch, $beforeQuantity, $target)],
                $beforeAvailable,
                $beforePrice,
                new StockOperationContext(
                    type: 'batch_correction',
                    actorId: $this->actorId(),
                    reason: $reason,
                    receivedReference: $lockedBatch->received_reference,
                ),
            );
        });
    }

    private function lockAggregate(Pharmacy $pharmacy, InventoryItem $aggregate): InventoryItem
    {
        $locked = InventoryItem::query()
            ->with('medicine')
            ->whereKey($aggregate->getKey())
            ->where('pharmacy_id', $pharmacy->getKey())
            ->whereHas('medicine')
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw new ForeignInventoryRecord;
        }

        return $locked;
    }

    /**
     * @return array{InventoryItem, InventoryBatch}
     */
    private function lockBatch(Pharmacy $pharmacy, InventoryBatch $batch): array
    {
        $lockedAggregate = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->getKey())
            ->whereHas('medicine')
            ->whereHas('batches', fn (Builder $query): Builder => $query->whereKey($batch->getKey()))
            ->lockForUpdate()
            ->first();

        if ($lockedAggregate === null) {
            throw new ForeignInventoryRecord;
        }

        $lockedBatch = InventoryBatch::query()
            ->whereKey($batch->getKey())
            ->where('inventory_item_id', $lockedAggregate->getKey())
            ->lockForUpdate()
            ->first();

        if ($lockedBatch === null) {
            throw new ForeignInventoryRecord;
        }

        return [$lockedAggregate, $lockedBatch];
    }

    private function assertColdChainAllowed(InventoryItem $aggregate, bool $coldChain): void
    {
        if ($aggregate->medicine?->cold_chain_required && ! $coldChain) {
            throw new ColdChainRequired;
        }
    }

    private function assertUniqueIdentity(
        InventoryItem $aggregate,
        string $identityKey,
        string $batchNumber,
        ?string $lotNumber,
        ?InventoryBatch $except = null,
    ): void {
        $query = $aggregate->batches();

        if ($except !== null) {
            $query->whereKeyNot($except->getKey());
        }

        if ((clone $query)->where('identity_key', $identityKey)->exists()) {
            throw new DuplicateBatchIdentity($batchNumber, $lotNumber);
        }

        $normalizedBatch = BatchIdentity::normalize($batchNumber);
        $normalizedLot = BatchIdentity::normalize($lotNumber ?? '');
        $hasTransitionalDuplicate = (clone $query)
            ->select(['id', 'batch_number', 'lot_number'])
            ->where('identity_key', 'not like', BatchIdentity::DIGEST_PREFIX.'%')
            ->lockForUpdate()
            ->get()
            ->contains(
                fn (InventoryBatch $candidate): bool => BatchIdentity::normalize($candidate->batch_number) === $normalizedBatch
                    && BatchIdentity::normalize($candidate->lot_number ?? '') === $normalizedLot
            );

        if ($hasTransitionalDuplicate) {
            throw new DuplicateBatchIdentity($batchNumber, $lotNumber);
        }
    }

    /**
     * @return array{?int, ?string}
     */
    private function resolveSupplier(?string $supplierName, ?int $supplierId): array
    {
        if ($supplierName !== null) {
            $normalizedName = $this->normalizedSupplierName($supplierName);
            $supplier = Supplier::query()
                ->select(['id', 'name'])
                ->orderBy('id')
                ->get()
                ->first(
                    fn (Supplier $candidate): bool => $this->normalizedSupplierName($candidate->name) === $normalizedName
                );

            $supplier ??= Supplier::query()->create(['name' => $supplierName]);

            return [(int) $supplier->getKey(), $supplierName];
        }

        if ($supplierId === null) {
            return [null, null];
        }

        $supplier = Supplier::query()->find($supplierId);

        if ($supplier === null) {
            throw new InvalidArgumentException('The selected supplier does not exist.');
        }

        return [(int) $supplier->getKey(), $supplier->name];
    }

    private function normalizedSupplierName(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', $this->trim($name));

        if (! is_string($normalized)) {
            throw new InvalidArgumentException('Supplier name must be valid UTF-8.');
        }

        return mb_strtolower($normalized, 'UTF-8');
    }

    private function requiredText(string $value, string $field, int $maxLength): string
    {
        $value = $this->trim($value);

        if ($value === '') {
            throw new InvalidArgumentException("{$field} must contain at least one non-whitespace character.");
        }

        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new InvalidArgumentException("{$field} may not be greater than {$maxLength} characters.");
        }

        return $value;
    }

    private function optionalText(?string $value, string $field, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = $this->trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new InvalidArgumentException("{$field} may not be greater than {$maxLength} characters.");
        }

        return $value;
    }

    private function trim(string $value): string
    {
        $trimmed = preg_replace('/^\s+|\s+$/u', '', $value);

        if (! is_string($trimmed)) {
            throw new InvalidArgumentException('Text values must be valid UTF-8.');
        }

        return $trimmed;
    }

    private function price(string $value): string
    {
        $value = trim($value);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $value, $matches)) {
            throw new InvalidArgumentException('Batch price must be non-negative with at most two decimal places.');
        }

        $integer = ltrim($matches[1], '0');
        $integer = $integer === '' ? '0' : $integer;

        if (strlen($integer) > 8) {
            throw new InvalidArgumentException('Batch price exceeds the supported maximum of 99999999.99.');
        }

        return $integer.'.'.str_pad($matches[2] ?? '', 2, '0');
    }

    private function actorId(): ?int
    {
        $actorId = auth()->id();

        return $actorId === null ? null : (int) $actorId;
    }
}
