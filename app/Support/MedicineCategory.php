<?php

namespace App\Support;

final class MedicineCategory
{
    /**
     * @return array<string, string>
     */
    public static function canonicalOptions(): array
    {
        return [
            'analgesic' => 'Analgesic',
            'antibiotic' => 'Antibiotic',
            'antidiarrheal' => 'Antidiarrheal',
            'antihistamine' => 'Antihistamine',
            'nsaid' => 'NSAID',
            'controlled' => 'Controlled',
            'vitamin' => 'Vitamin',
            'supplement' => 'Supplement',
            'other' => 'Other',
        ];
    }

    /**
     * @param  iterable<mixed>  $storedCategories
     * @return array<string, string>
     */
    public static function optionsWithCustom(iterable $storedCategories): array
    {
        $options = self::canonicalOptions();
        $known = array_fill_keys(array_keys($options), true);

        foreach ($storedCategories as $storedCategory) {
            if (! is_string($storedCategory)) {
                continue;
            }

            $label = trim($storedCategory);
            $key = self::key($label);

            if ($key === '' || isset($known[$key])) {
                continue;
            }

            $options[$label] = $label;
            $known[$key] = true;
        }

        return $options;
    }

    public static function optionValue(?string $storedCategory): string
    {
        $value = trim((string) $storedCategory);
        $key = self::key($value);

        return array_key_exists($key, self::canonicalOptions()) ? $key : $value;
    }

    private static function key(string $category): string
    {
        return mb_strtolower(trim($category));
    }
}
