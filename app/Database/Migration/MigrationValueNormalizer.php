<?php

namespace App\Database\Migration;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

final class MigrationValueNormalizer
{
    /**
     * Normalize a SQLite value for a declared PostgreSQL destination type.
     *
     * @param  array{type: string, nullable: bool, precision?: int, scale?: int}  $definition
     */
    public function normalize(
        mixed $value,
        array $definition,
        string $table,
        int|string|null $primaryKey,
        string $column
    ): mixed {
        if ($value === null) {
            if ($definition['nullable']) {
                return null;
            }

            throw $this->invalid($table, $primaryKey, $column, 'required_value_is_null');
        }

        return match ($definition['type']) {
            'integer' => $this->integer($value, $table, $primaryKey, $column),
            'boolean' => $this->boolean($value, $table, $primaryKey, $column),
            'decimal' => $this->decimal($value, $definition, $table, $primaryKey, $column),
            'json' => $this->json($value, $table, $primaryKey, $column),
            'date' => $this->date($value, $table, $primaryKey, $column),
            'timestamp' => $this->timestamp($value, $table, $primaryKey, $column),
            'uuid' => $this->uuid($value, $table, $primaryKey, $column),
            'string', 'opaque' => $this->utf8String($value, $table, $primaryKey, $column),
            default => throw $this->invalid($table, $primaryKey, $column, 'unsupported_normalization_type'),
        };
    }

    /**
     * Produce a stable representation for source/target equivalence hashes.
     *
     * @param  array{type: string, nullable: bool, precision?: int, scale?: int}  $definition
     */
    public function canonicalize(
        mixed $value,
        array $definition,
        string $table,
        int|string|null $primaryKey,
        string $column
    ): array {
        $normalized = $this->normalize($value, $definition, $table, $primaryKey, $column);

        if ($normalized === null) {
            return ['type' => 'null'];
        }

        $canonical = match ($definition['type']) {
            'integer' => (string) $normalized,
            'boolean' => $normalized ? 'true' : 'false',
            'decimal' => $this->canonicalDecimal($normalized, (int) $definition['scale']),
            'json' => $this->canonicalJson($normalized, $table, $primaryKey, $column),
            'uuid' => strtolower($normalized),
            default => $normalized,
        };

        return ['type' => $definition['type'], 'value' => $canonical];
    }

    private function integer(mixed $value, string $table, int|string|null $primaryKey, string $column): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) !== 1) {
            throw $this->invalid($table, $primaryKey, $column, 'invalid_integer');
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        if ($filtered === false) {
            throw $this->invalid($table, $primaryKey, $column, 'integer_out_of_range');
        }

        return $filtered;
    }

    private function boolean(mixed $value, string $table, int|string|null $primaryKey, string $column): bool
    {
        return match (true) {
            $value === true, $value === 1, $value === '1' => true,
            $value === false, $value === 0, $value === '0' => false,
            default => throw $this->invalid($table, $primaryKey, $column, 'invalid_boolean'),
        };
    }

    /**
     * @param  array{type: string, nullable: bool, precision?: int, scale?: int}  $definition
     */
    private function decimal(
        mixed $value,
        array $definition,
        string $table,
        int|string|null $primaryKey,
        string $column
    ): string {
        if (! is_string($value) && ! is_int($value)) {
            throw $this->invalid($table, $primaryKey, $column, 'decimal_must_not_use_binary_float');
        }

        $decimal = (string) $value;

        if (preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $decimal) !== 1) {
            throw $this->invalid($table, $primaryKey, $column, 'invalid_decimal');
        }

        $unsigned = ltrim($decimal, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $precision = $definition['precision'] ?? null;
        $scale = $definition['scale'] ?? null;

        if (! is_int($precision) || ! is_int($scale) || $scale < 0 || $precision < $scale) {
            throw $this->invalid($table, $primaryKey, $column, 'invalid_decimal_definition');
        }

        if (strlen($fraction) > $scale || strlen(ltrim($whole, '0')) > ($precision - $scale)) {
            throw $this->invalid($table, $primaryKey, $column, 'decimal_out_of_range');
        }

        return $decimal;
    }

    private function json(mixed $value, string $table, int|string|null $primaryKey, string $column): string
    {
        $json = $this->utf8String($value, $table, $primaryKey, $column);

        try {
            json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw $this->invalid($table, $primaryKey, $column, 'invalid_json', $exception);
        }

        return $json;
    }

    private function date(mixed $value, string $table, int|string|null $primaryKey, string $column): string
    {
        $date = $this->utf8String($value, $table, $primaryKey, $column);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d') !== $date) {
            throw $this->invalid($table, $primaryKey, $column, 'invalid_date');
        }

        return $date;
    }

    private function timestamp(mixed $value, string $table, int|string|null $primaryKey, string $column): string
    {
        $timestamp = $this->utf8String($value, $table, $primaryKey, $column);
        $format = str_contains($timestamp, '.') ? '!Y-m-d H:i:s.u' : '!Y-m-d H:i:s';
        $parsed = DateTimeImmutable::createFromFormat($format, $timestamp, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        $renderFormat = str_contains($timestamp, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';

        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format($renderFormat) !== $timestamp) {
            throw $this->invalid($table, $primaryKey, $column, 'invalid_timestamp');
        }

        return $timestamp;
    }

    private function uuid(mixed $value, string $table, int|string|null $primaryKey, string $column): string
    {
        $uuid = $this->utf8String($value, $table, $primaryKey, $column);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $uuid) !== 1) {
            throw $this->invalid($table, $primaryKey, $column, 'invalid_uuid');
        }

        return $uuid;
    }

    private function utf8String(mixed $value, string $table, int|string|null $primaryKey, string $column): string
    {
        if (! is_string($value) || preg_match('//u', $value) !== 1) {
            throw $this->invalid($table, $primaryKey, $column, 'invalid_utf8_string');
        }

        return $value;
    }

    private function canonicalDecimal(string $decimal, int $scale): string
    {
        $negative = str_starts_with($decimal, '-');
        $unsigned = $negative ? substr($decimal, 1) : $decimal;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($fraction, $scale, '0');
        $sign = $negative && ($whole !== '0' || trim($fraction, '0') !== '') ? '-' : '';

        return $sign.$whole.($scale > 0 ? '.'.$fraction : '');
    }

    private function canonicalJson(
        string $json,
        string $table,
        int|string|null $primaryKey,
        string $column
    ): string {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $sorted = $this->sortJson($decoded);

            return json_encode(
                $sorted,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw $this->invalid($table, $primaryKey, $column, 'invalid_json', $exception);
        }
    }

    private function sortJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $nested) {
            $value[$key] = $this->sortJson($nested);
        }

        return $value;
    }

    private function invalid(
        string $table,
        int|string|null $primaryKey,
        string $column,
        string $reason,
        ?\Throwable $previous = null
    ): MigrationTransferException {
        return new MigrationTransferException($reason, $table, $primaryKey, $column, $previous);
    }
}
