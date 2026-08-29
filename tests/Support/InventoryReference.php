<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;

final class InventoryReference
{
    public static function normalizeIdentity(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)), 'UTF-8');
    }

    /** @param array<int, array{quantity: int, expiry: ?string}> $batches */
    public static function availableStock(array $batches, CarbonImmutable $asOf): int
    {
        return array_sum(array_map(
            static fn (array $batch): int => $batch['quantity'] > 0
                && ($batch['expiry'] === null || $batch['expiry'] >= $asOf->toDateString())
                    ? $batch['quantity']
                    : 0,
            $batches
        ));
    }

    /** @param array<int, array{quantity: int}> $batches */
    public static function physicalStock(array $batches): int
    {
        return array_sum(array_map(static fn (array $batch): int => max(0, $batch['quantity']), $batches));
    }
}
