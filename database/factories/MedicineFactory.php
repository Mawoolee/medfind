<?php

namespace Database\Factories;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    protected $model = Medicine::class;

    private static array $names = [
        'Paracetamol', 'Amoxicillin', 'Ibuprofen', 'Cetirizine', 'Loperamide',
        'Mefenamic Acid', 'Losartan', 'Metformin', 'Omeprazole', 'Ascorbic Acid',
    ];

    private static array $categories = ['Analgesic', 'Antibiotic', 'Antihistamine', 'Antidiarrheal', 'NSAID'];

    public function definition(): array
    {
        return [
            'medicine_name' => fake()->randomElement(self::$names).' '.fake()->numberBetween(1, 100).'mg',
            'brand_name' => fake()->optional()->company(),
            'dosage' => fake()->randomElement(['500mg', '250mg', '100mg', '10mg', '5mg']),
            'manufacturer' => fake()->company(),
            'requiresPrescription' => false,
            'cold_chain_required' => false,
            'category' => fake()->randomElement(self::$categories),
        ];
    }

    public function prescription(): static
    {
        return $this->state(['requiresPrescription' => true]);
    }

    public function coldChainRequired(): static
    {
        return $this->state(['cold_chain_required' => true]);
    }

    public function named(string $name): static
    {
        return $this->state(['medicine_name' => $name]);
    }
}
