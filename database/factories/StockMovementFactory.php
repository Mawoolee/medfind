<?php

namespace Database\Factories;

use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockMovement> */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $before = fake()->numberBetween(0, 200);
        $after = fake()->numberBetween(0, 200);

        return [
            'operation_id' => (string) Str::uuid(),
            'inventory_batch_id' => InventoryBatch::factory(),
            'inventory_item_id' => fn (array $attributes): int => (int) InventoryBatch::query()->findOrFail($attributes['inventory_batch_id'])->inventory_item_id,
            'type' => 'batch_correction',
            'before_quantity' => $before,
            'after_quantity' => $after,
            'quantity_delta' => $after - $before,
            'reason' => fake()->sentence(),
            'reference_type' => null,
            'reference_id' => null,
            'received_reference' => null,
            'user_id' => null,
            'created_at' => now(),
        ];
    }
}
