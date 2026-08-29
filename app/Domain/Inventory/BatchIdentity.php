<?php

namespace App\Domain\Inventory;

use InvalidArgumentException;

final class BatchIdentity
{
    public static function key(string $batchNumber, ?string $lotNumber): string
    {
        $batch = self::normalize($batchNumber);

        if ($batch === '') {
            throw new InvalidArgumentException('Batch number must contain at least one non-whitespace character.');
        }

        return 'batch:'.$batch.'|lot:'.self::normalize($lotNumber ?? '');
    }

    public static function legacy(int $inventoryItemId): string
    {
        if ($inventoryItemId < 1) {
            throw new InvalidArgumentException('Legacy inventory item identifiers must be positive.');
        }

        return 'legacy:'.$inventoryItemId;
    }

    public static function legacyBatchNumber(int $inventoryItemId): string
    {
        if ($inventoryItemId < 1) {
            throw new InvalidArgumentException('Legacy inventory item identifiers must be positive.');
        }

        return 'LEGACY-'.$inventoryItemId;
    }

    public static function normalize(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        if (! is_string($normalized)) {
            throw new InvalidArgumentException('Batch identity values must be valid UTF-8.');
        }

        return mb_strtolower($normalized, 'UTF-8');
    }
}
