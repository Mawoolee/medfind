<?php

namespace Database\Factories;

use App\Domain\Inventory\BatchIdentity;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryBatch> */
class InventoryBatchFactory extends Factory
{
    protected $model = InventoryBatch::class;

    public function definition(): array
    {
        $batchNumber = 'BATCH-'.fake()->unique()->bothify('####-????');
        $lotNumber = fake()->optional()->bothify('LOT-####');
        $quantity = fake()->numberBetween(0, 200);

        return [
            'inventory_item_id' => InventoryItem::factory(),
            'legacy_source_inventory_item_id' => null,
            'batch_number' => $batchNumber,
            'lot_number' => $lotNumber,
            'identity_key' => BatchIdentity::key($batchNumber, $lotNumber),
            'quantity_received' => $quantity,
            'current_quantity' => $quantity,
            'price' => fake()->randomFloat(2, 0, 500),
            'supplier_id' => null,
            'supplier_name' => fake()->optional()->company(),
            'expiry_date' => fake()->optional()->dateTimeBetween('+1 day', '+2 years')?->format('Y-m-d'),
            'cold_chain' => false,
            'received_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'received_reference' => fake()->optional()->bothify('DEL-####'),
            'created_by' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => fake()->dateTimeBetween('-2 years', '-1 day')->format('Y-m-d'),
        ]);
    }

    public function depleted(): static
    {
        return $this->state(['current_quantity' => 0]);
    }

    public function coldChain(): static
    {
        return $this->state(['cold_chain' => true]);
    }
}
