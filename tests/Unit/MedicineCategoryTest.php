<?php

namespace Tests\Unit;

use App\Support\MedicineCategory;
use PHPUnit\Framework\TestCase;

class MedicineCategoryTest extends TestCase
{
    public function test_canonical_options_have_stable_values_and_labels(): void
    {
        $this->assertSame([
            'analgesic' => 'Analgesic',
            'antibiotic' => 'Antibiotic',
            'antidiarrheal' => 'Antidiarrheal',
            'antihistamine' => 'Antihistamine',
            'nsaid' => 'NSAID',
            'controlled' => 'Controlled',
            'vitamin' => 'Vitamin',
            'supplement' => 'Supplement',
            'other' => 'Other',
        ], MedicineCategory::canonicalOptions());
    }

    public function test_custom_options_are_trimmed_and_deduplicated_case_insensitively(): void
    {
        $options = MedicineCategory::optionsWithCustom([
            'Analgesic',
            '  Specialty Care  ',
            'specialty care',
            '   ',
            null,
        ]);

        $this->assertSame(
            MedicineCategory::canonicalOptions() + ['Specialty Care' => 'Specialty Care'],
            $options,
        );
    }

    public function test_option_value_normalizes_canonical_categories_and_preserves_custom_labels(): void
    {
        $this->assertSame('analgesic', MedicineCategory::optionValue(' Analgesic '));
        $this->assertSame('Legacy Compound', MedicineCategory::optionValue(' Legacy Compound '));
        $this->assertSame('', MedicineCategory::optionValue(null));
    }
}
