<?php

namespace Tests\Unit;

use App\Database\Migration\MigrationTransferException;
use App\Database\Migration\MigrationValueNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationValueNormalizerTest extends TestCase
{
    private MigrationValueNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new MigrationValueNormalizer;
    }

    /**
     * **Validates: Requirements 1.3, 2.3, 3.1, 3.5**
     *
     * @return iterable<string, array{0: mixed, 1: array<string, mixed>, 2: mixed}>
     */
    public static function validValues(): iterable
    {
        yield 'boolean integer zero' => [0, ['type' => 'boolean', 'nullable' => false], false];
        yield 'boolean string one' => ['1', ['type' => 'boolean', 'nullable' => false], true];
        yield 'exact decimal remains a string' => ['999999.99', ['type' => 'decimal', 'nullable' => false, 'precision' => 10, 'scale' => 2], '999999.99'];
        yield 'semantic JSON with unicode' => ['{"z":1,"a":"✓"}', ['type' => 'json', 'nullable' => false], '{"z":1,"a":"✓"}'];
        yield 'leap day' => ['2024-02-29', ['type' => 'date', 'nullable' => false], '2024-02-29'];
        yield 'microsecond timestamp' => ['2026-12-31 23:59:59.123456', ['type' => 'timestamp', 'nullable' => false], '2026-12-31 23:59:59.123456'];
        yield 'UUID' => ['12345678-1234-4abc-8def-1234567890ab', ['type' => 'uuid', 'nullable' => false], '12345678-1234-4abc-8def-1234567890ab'];
        yield 'empty string differs from null' => ['', ['type' => 'string', 'nullable' => false], ''];
        yield 'nullable SQL value' => [null, ['type' => 'json', 'nullable' => true], null];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    #[DataProvider('validValues')]
    public function test_strict_normalization_preserves_valid_semantics(
        mixed $value,
        array $definition,
        mixed $expected
    ): void {
        self::assertSame(
            $expected,
            $this->normalizer->normalize($value, $definition, 'fixture', 41, 'value')
        );
    }

    /**
     * Generated property: every value outside the accepted SQLite forms is rejected
     * with context but without embedding the invalid row value.
     *
     * **Validates: Requirements 1.3, 2.3, 2.6, 3.1**
     */
    public function test_generated_invalid_typed_values_fail_closed_without_payload_disclosure(): void
    {
        $secret = 'payload-that-must-not-be-disclosed';
        $cases = [
            [$secret, ['type' => 'boolean', 'nullable' => false], 'invalid_boolean'],
            [2, ['type' => 'boolean', 'nullable' => false], 'invalid_boolean'],
            [12.34, ['type' => 'decimal', 'nullable' => false, 'precision' => 10, 'scale' => 2], 'decimal_must_not_use_binary_float'],
            ['12.345', ['type' => 'decimal', 'nullable' => false, 'precision' => 10, 'scale' => 2], 'decimal_out_of_range'],
            ['{"broken":', ['type' => 'json', 'nullable' => false], 'invalid_json'],
            ['2023-02-29', ['type' => 'date', 'nullable' => false], 'invalid_date'],
            ['2026-13-01 00:00:00', ['type' => 'timestamp', 'nullable' => false], 'invalid_timestamp'],
            ['not-a-uuid', ['type' => 'uuid', 'nullable' => false], 'invalid_uuid'],
            [null, ['type' => 'string', 'nullable' => false], 'required_value_is_null'],
        ];

        foreach ($cases as $index => [$value, $definition, $reason]) {
            try {
                $this->normalizer->normalize($value, $definition, 'fixture_table', 100 + $index, 'typed_column');
                self::fail("Case {$index} must be rejected.");
            } catch (MigrationTransferException $exception) {
                self::assertSame($reason, $exception->getMessage());
                self::assertSame('fixture_table', $exception->table);
                self::assertSame(100 + $index, $exception->primaryKey);
                self::assertSame('typed_column', $exception->column);
                self::assertStringNotContainsString($secret, json_encode($exception->safeContext(), JSON_THROW_ON_ERROR));
            }
        }
    }

    /**
     * Property: semantically equivalent JSON and decimals have identical canonical forms.
     *
     * **Validates: Requirements 2.3, 3.1, 3.5**
     */
    public function test_generated_semantically_equivalent_values_have_stable_canonical_forms(): void
    {
        for ($sample = 0; $sample < 32; $sample++) {
            $jsonA = json_encode(['z' => $sample, 'a' => ['unicode' => '✓', 'null' => null]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $jsonB = json_encode(['a' => ['null' => null, 'unicode' => '✓'], 'z' => $sample], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $jsonDefinition = ['type' => 'json', 'nullable' => false];

            self::assertSame(
                $this->normalizer->canonicalize($jsonA, $jsonDefinition, 'fixture', $sample, 'json_value'),
                $this->normalizer->canonicalize($jsonB, $jsonDefinition, 'fixture', $sample, 'json_value')
            );

            $decimalDefinition = ['type' => 'decimal', 'nullable' => false, 'precision' => 10, 'scale' => 2];
            self::assertSame(
                $this->normalizer->canonicalize((string) $sample, $decimalDefinition, 'fixture', $sample, 'decimal_value'),
                $this->normalizer->canonicalize($sample.'.00', $decimalDefinition, 'fixture', $sample, 'decimal_value')
            );
        }
    }
}
