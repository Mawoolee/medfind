<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'pharmacy_id'   => Pharmacy::factory(),
            'medicine_id'   => Medicine::factory(),
            'stockQuantity' => fake()->numberBetween(10, 200),
            'price'         => fake()->randomFloat(2, 10, 500),
            'status'        => 'available',
            'expiry_date'   => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'batch_number'  => 'BATCH-' . fake()->numberBetween(1000, 9999),
            'cold_chain'    => false,
            'par_level'     => fake()->numberBetween(5, 20),
            'supplier_id'   => null,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['stockQuantity' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(['stockQuantity' => 2, 'par_level' => 10]);
    }
}
