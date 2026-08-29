<?php

namespace Database\Factories;

use App\Domain\Inventory\BatchIdentity;
use App\Models\InventoryBatch;
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
            'pharmacy_id' => Pharmacy::factory(),
            'medicine_id' => Medicine::factory(),
            'stockQuantity' => fake()->numberBetween(10, 200),
            'price' => fake()->randomFloat(2, 10, 500),
            'status' => 'available',
            'expiry_date' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'batch_number' => 'BATCH-'.fake()->numberBetween(1000, 9999),
            'lot_number' => 'LOT-'.fake()->numberBetween(1000, 9999),
            'cold_chain' => false,
            'par_level' => fake()->numberBetween(5, 20),
            'supplier_id' => null,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['stockQuantity' => 0, 'status' => 'out_of_stock']);
    }

    public function lowStock(): static
    {
        return $this->state(['stockQuantity' => 2, 'par_level' => 10, 'status' => 'low_stock']);
    }

    public function withBatch(array $attributes = []): static
    {
        return $this->afterCreating(function (InventoryItem $aggregate) use ($attributes): void {
            InventoryBatch::factory()->create(array_merge([
                'inventory_item_id' => $aggregate->id,
                'batch_number' => $aggregate->batch_number,
                'lot_number' => $aggregate->lot_number,
                'identity_key' => BatchIdentity::key($aggregate->batch_number, $aggregate->lot_number),
                'quantity_received' => $aggregate->stockQuantity,
                'current_quantity' => $aggregate->stockQuantity,
                'price' => $aggregate->price,
                'supplier_id' => $aggregate->supplier_id,
                'expiry_date' => $aggregate->expiry_date,
                'cold_chain' => $aggregate->cold_chain,
            ], $attributes));
        });
    }
}
