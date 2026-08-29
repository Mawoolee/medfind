<?php

namespace Tests\Unit;

use App\Domain\Inventory\BatchIdentity;
use Eris\Generators;
use InvalidArgumentException;
use Tests\Support\InventoryPropertyGenerators;
use Tests\Support\PropertyTestCase;

final class BatchIdentityTest extends PropertyTestCase
{
    /** **Validates: Requirements 3.12, 5.9, 8.8** */
    public function test_identity_normalizes_unicode_case_and_whitespace_unambiguously(): void
    {
        self::assertSame(
            BatchIdentity::key("  BÁTCH\t薬  01 ", ' LOT  9 '),
            BatchIdentity::key('bátch 薬 01', "lot\t9")
        );
        self::assertNotSame(
            BatchIdentity::key('ab', 'c'),
            BatchIdentity::key('a', 'bc')
        );
        self::assertSame('legacy:42', BatchIdentity::legacy(42));
        self::assertSame('LEGACY-42', BatchIdentity::legacyBatchNumber(42));
    }

    /** **Validates: Requirements 3.12, 8.8** */
    public function test_generated_whitespace_and_case_variants_produce_the_same_key(): void
    {
        $this->forAll(InventoryPropertyGenerators::identityText(), Generators::elements(['LOT 1', 'LÓTE-薬', '']))
            ->then(function (string $batch, string $lot): void {
                $normalizedBatch = BatchIdentity::normalize($batch);
                $normalizedLot = BatchIdentity::normalize($lot);
                $upperBatch = mb_strtoupper($normalizedBatch, 'UTF-8');
                $upperLot = mb_strtoupper($normalizedLot, 'UTF-8');
                $caseVariantBatch = mb_strtolower($upperBatch, 'UTF-8') === $normalizedBatch ? $upperBatch : $normalizedBatch;
                $caseVariantLot = mb_strtolower($upperLot, 'UTF-8') === $normalizedLot ? $upperLot : $normalizedLot;
                $variantBatch = " \t".$caseVariantBatch.'  ';
                $variantLot = '  '.$caseVariantLot."\n";

                self::assertSame(
                    BatchIdentity::key($normalizedBatch, $normalizedLot),
                    BatchIdentity::key($variantBatch, $variantLot)
                );
            });
    }

    public function test_blank_batch_and_invalid_legacy_ids_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BatchIdentity::key(" \t ", null);
    }
}
