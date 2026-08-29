<?php

namespace App\Database\Migration;

use App\Domain\Inventory\BatchIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use RuntimeException;

final class LegacyInventoryBackfill
{
    /**
     * @param  null|callable(Connection): void  $afterVerification
     */
    public function run(Connection $connection, CarbonImmutable $migrationDate, ?callable $afterVerification = null): void
    {
        $connection->transaction(function () use ($connection, $migrationDate, $afterVerification): void {
            $connection->table('inventory_items')->orderBy('id')->chunkById(200, function ($items) use ($connection, $migrationDate): void {
                foreach ($items as $item) {
                    $this->backfillItem($connection, $item, $migrationDate);
                }
            });

            $this->verifyAll($connection, $migrationDate);

            if ($afterVerification !== null) {
                $afterVerification($connection);
            }
        });
    }

    private function backfillItem(Connection $connection, object $item, CarbonImmutable $migrationDate): void
    {
        $quantity = $this->nonNegativeInteger($item->stockQuantity, 'stockQuantity', (int) $item->id);
        $price = $this->nonNegativeDecimal($item->price, 'price', (int) $item->id);
        $batchNumber = $this->displayBatchNumber($item->batch_number ?? null, (int) $item->id);
        $lotNumber = $item->lot_number ?? null;
        $identityKey = $this->hasText($item->batch_number ?? null)
            ? BatchIdentity::key($batchNumber, $lotNumber)
            : BatchIdentity::legacy((int) $item->id);
        $createdAt = $item->created_at ?? null;
        $updatedAt = $item->updated_at ?? $createdAt;
        $receivedDate = $createdAt !== null
            ? CarbonImmutable::parse((string) $createdAt)->toDateString()
            : $migrationDate->toDateString();
        $supplierName = null;

        if (($item->supplier_id ?? null) !== null) {
            $supplierName = $connection->table('suppliers')->where('id', $item->supplier_id)->value('name');

            if ($supplierName === null) {
                throw new RuntimeException("Legacy inventory item {$item->id} references a missing supplier.");
            }
        }

        $batch = $connection->table('inventory_batches')
            ->where('legacy_source_inventory_item_id', $item->id)
            ->first();

        if ($batch === null) {
            $batchId = $connection->table('inventory_batches')->insertGetId([
                'inventory_item_id' => $item->id,
                'legacy_source_inventory_item_id' => $item->id,
                'batch_number' => $batchNumber,
                'lot_number' => $lotNumber,
                'identity_key' => $identityKey,
                'quantity_received' => $quantity,
                'current_quantity' => $quantity,
                'price' => $price,
                'supplier_id' => $item->supplier_id ?? null,
                'supplier_name' => $supplierName,
                'expiry_date' => $item->expiry_date ?? null,
                'cold_chain' => (bool) ($item->cold_chain ?? false),
                'received_date' => $receivedDate,
                'received_reference' => 'legacy-inventory:'.$item->id,
                'created_by' => null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
            $batch = $connection->table('inventory_batches')->where('id', $batchId)->first();
        }

        if ($batch === null) {
            throw new RuntimeException("Legacy inventory item {$item->id} could not be backfilled.");
        }

        $operationId = 'legacy-backfill:'.$item->id;
        $connection->table('stock_movements')->insertOrIgnore([
            'operation_id' => $operationId,
            'inventory_item_id' => $item->id,
            'inventory_batch_id' => $batch->id,
            'type' => 'backfill',
            'before_quantity' => 0,
            'after_quantity' => $quantity,
            'quantity_delta' => $quantity,
            'reason' => 'Legacy inventory migration',
            'reference_type' => 'inventory_item',
            'reference_id' => (string) $item->id,
            'received_reference' => 'legacy-inventory:'.$item->id,
            'user_id' => null,
            'created_at' => $createdAt ?? $migrationDate->startOfDay()->toDateTimeString(),
        ]);

        $this->synchronizeAggregate($connection, (int) $item->id, (int) ($item->par_level ?? 0), $migrationDate);
    }

    private function verifyAll(Connection $connection, CarbonImmutable $migrationDate): void
    {
        $connection->table('inventory_items')->orderBy('id')->chunkById(200, function ($items) use ($connection, $migrationDate): void {
            foreach ($items as $item) {
                $matches = $connection->table('inventory_batches')
                    ->where('legacy_source_inventory_item_id', $item->id)
                    ->get();

                if ($matches->count() !== 1) {
                    throw new RuntimeException("Legacy inventory item {$item->id} does not have exactly one source-linked batch.");
                }

                $batch = $matches->first();
                $quantity = $this->nonNegativeInteger($item->stockQuantity, 'stockQuantity', (int) $item->id, allowProjection: true);
                $legacyQuantity = $this->expectedLegacyQuantity($connection, (int) $item->id, $batch);
                $expectedBatchNumber = $this->displayBatchNumber($item->batch_number ?? null, (int) $item->id);
                $expectedIdentity = $this->hasText($item->batch_number ?? null)
                    ? BatchIdentity::key($expectedBatchNumber, $item->lot_number ?? null)
                    : BatchIdentity::legacy((int) $item->id);
                $expectedReceivedDate = ($item->created_at ?? null) !== null
                    ? CarbonImmutable::parse((string) $item->created_at)->toDateString()
                    : $migrationDate->toDateString();
                $expectedSupplierName = ($item->supplier_id ?? null) === null
                    ? null
                    : $connection->table('suppliers')->where('id', $item->supplier_id)->value('name');

                $expected = [
                    'inventory_item_id' => (int) $item->id,
                    'quantity_received' => $legacyQuantity,
                    'current_quantity' => $legacyQuantity,
                    'batch_number' => $expectedBatchNumber,
                    'lot_number' => $item->lot_number ?? null,
                    'identity_key' => $expectedIdentity,
                    'price' => $this->canonicalDecimal($item->price),
                    'expiry_date' => $this->canonicalDate($item->expiry_date ?? null),
                    'cold_chain' => (bool) ($item->cold_chain ?? false),
                    'supplier_id' => ($item->supplier_id ?? null) === null ? null : (int) $item->supplier_id,
                    'supplier_name' => $expectedSupplierName,
                    'received_date' => $expectedReceivedDate,
                    'received_reference' => 'legacy-inventory:'.$item->id,
                    'created_at' => $this->canonicalTimestamp($item->created_at ?? null),
                    'updated_at' => $this->canonicalTimestamp(($item->updated_at ?? null) ?? ($item->created_at ?? null)),
                ];
                $actual = [
                    'inventory_item_id' => (int) $batch->inventory_item_id,
                    'quantity_received' => (int) $batch->quantity_received,
                    'current_quantity' => (int) $batch->current_quantity,
                    'batch_number' => (string) $batch->batch_number,
                    'lot_number' => $batch->lot_number ?? null,
                    'identity_key' => (string) $batch->identity_key,
                    'price' => $this->canonicalDecimal($batch->price),
                    'expiry_date' => $this->canonicalDate($batch->expiry_date ?? null),
                    'cold_chain' => (bool) $batch->cold_chain,
                    'supplier_id' => ($batch->supplier_id ?? null) === null ? null : (int) $batch->supplier_id,
                    'supplier_name' => $batch->supplier_name ?? null,
                    'received_date' => $this->canonicalDate($batch->received_date),
                    'received_reference' => (string) $batch->received_reference,
                    'created_at' => $this->canonicalTimestamp($batch->created_at ?? null),
                    'updated_at' => $this->canonicalTimestamp($batch->updated_at ?? null),
                ];

                foreach ($expected as $field => $value) {
                    if ($actual[$field] !== $value) {
                        throw new RuntimeException("Legacy inventory item {$item->id} failed exact {$field} verification.");
                    }
                }

                $movementCount = $connection->table('stock_movements')
                    ->where('operation_id', 'legacy-backfill:'.$item->id)
                    ->where('inventory_batch_id', $batch->id)
                    ->where('type', 'backfill')
                    ->count();

                if ($movementCount !== 1) {
                    throw new RuntimeException("Legacy inventory item {$item->id} does not have exactly one backfill movement.");
                }

                $available = $this->availableQuantity($connection, (int) $item->id, $migrationDate);
                if ($quantity !== $available) {
                    throw new RuntimeException("Legacy inventory item {$item->id} aggregate projection does not match available batch stock.");
                }
            }
        });
    }

    private function synchronizeAggregate(Connection $connection, int $inventoryItemId, int $parLevel, CarbonImmutable $asOf): void
    {
        $available = $this->availableQuantity($connection, $inventoryItemId, $asOf);
        $status = match (true) {
            $available === 0 => 'out_of_stock',
            $parLevel > 0 && $available <= $parLevel => 'low_stock',
            default => 'available',
        };

        $connection->table('inventory_items')->where('id', $inventoryItemId)->update([
            'stockQuantity' => $available,
            'status' => $status,
        ]);
    }

    private function availableQuantity(Connection $connection, int $inventoryItemId, CarbonImmutable $asOf): int
    {
        return (int) $connection->table('inventory_batches')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('current_quantity', '>', 0)
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', $asOf->toDateString());
            })
            ->sum('current_quantity');
    }

    private function expectedLegacyQuantity(Connection $connection, int $inventoryItemId, object $batch): int
    {
        $movement = $connection->table('stock_movements')
            ->where('operation_id', 'legacy-backfill:'.$inventoryItemId)
            ->where('inventory_batch_id', $batch->id)
            ->where('type', 'backfill')
            ->first();

        if ($movement === null) {
            throw new RuntimeException("Legacy inventory item {$inventoryItemId} is missing its backfill movement.");
        }

        return $this->nonNegativeInteger($movement->after_quantity, 'after_quantity', $inventoryItemId);
    }

    private function displayBatchNumber(mixed $value, int $inventoryItemId): string
    {
        if (! $this->hasText($value)) {
            return BatchIdentity::legacyBatchNumber($inventoryItemId);
        }

        $trimmed = preg_replace('/^\s+|\s+$/u', '', (string) $value);

        if (! is_string($trimmed) || $trimmed === '') {
            throw new RuntimeException("Legacy inventory item {$inventoryItemId} has an invalid batch number.");
        }

        return $trimmed;
    }

    private function hasText(mixed $value): bool
    {
        return is_string($value) && preg_match('/\S/u', $value) === 1;
    }

    private function nonNegativeInteger(mixed $value, string $column, int $id, bool $allowProjection = false): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            throw new RuntimeException("Legacy inventory item {$id} has an invalid {$column} value.");
        }

        return (int) $value;
    }

    private function nonNegativeDecimal(mixed $value, string $column, int $id): string
    {
        if (! is_numeric($value) || (float) $value < 0) {
            throw new RuntimeException("Legacy inventory item {$id} has an invalid {$column} value.");
        }

        return $this->canonicalDecimal($value);
    }

    private function canonicalDecimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function canonicalTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->format('Y-m-d H:i:s.u');
    }

    private function canonicalDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toDateString();
    }
}
