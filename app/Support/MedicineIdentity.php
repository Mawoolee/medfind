<?php

namespace App\Support;

final class MedicineIdentity
{
    public static function key(?string $medicineName, ?string $brandName, ?string $dosage): string
    {
        $identity = [
            self::normalize($medicineName),
            self::normalize($brandName),
            self::normalize($dosage),
        ];

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    private static function normalize(?string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return mb_strtolower($value ?? '', 'UTF-8');
    }
}
