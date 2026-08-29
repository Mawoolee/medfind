<?php

namespace Tests\Support;

use Eris\Generator;
use Eris\Generators;

final class InventoryPropertyGenerators
{
    public static function dateOffset(): Generator
    {
        return Generators::choose(-730, 730);
    }

    public static function identityText(): Generator
    {
        return Generators::elements([
            'BATCH-001', ' batch-001 ', "BATCH\t001", 'LÓTE-薬-01', 'ßETA  2', 'ΑΛΦΑ-3',
        ]);
    }

    public static function optionalIdentityText(): Generator
    {
        return Generators::oneOf(Generators::constant(null), self::identityText());
    }

    public static function batchVector(): Generator
    {
        return Generators::vector(8, Generators::tuple(
            Generators::choose(0, 500),
            self::dateOffset(),
            Generators::choose(0, 100_000)
        ));
    }

    public static function pharmacyOwnershipGraph(): Generator
    {
        return Generators::tuple(
            Generators::choose(1, 1_000_000),
            Generators::choose(1, 1_000_000),
            Generators::vector(6, Generators::choose(1, 1_000_000))
        );
    }
}
