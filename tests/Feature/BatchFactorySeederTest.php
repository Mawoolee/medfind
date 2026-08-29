<?php

namespace Tests\Feature;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\InventoryTestSeeder;
use Database\Seeders\SuppliersAndReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BatchFactorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_aware_factories_create_consistent_relationships(): void
    {
        $aggregate = InventoryItem::factory()->withBatch()->create([
            'stockQuantity' => 14,
            'price' => '12.50',
            'batch_number' => 'FACTORY-1',
            'lot_number' => 'LOT-1',
        ]);
        $batch = $aggregate->batches()->sole();
        $movement = StockMovement::factory()->create();

        self::assertSame(14, $batch->current_quantity);
        self::assertSame('12.50', $batch->price);
        self::assertSame($movement->batch->inventory_item_id, $movement->inventory_item_id);
        self::assertSame($movement->after_quantity - $movement->before_quantity, $movement->quantity_delta);
    }

    /** @return iterable<string, array{0: class-string} > */
    public static function inventorySeeders(): iterable
    {
        yield 'database seeder' => [DatabaseSeeder::class];
        yield 'demo seeder' => [DemoSeeder::class];
        yield 'inventory test seeder' => [InventoryTestSeeder::class];
        yield 'suppliers and receiving seeder' => [SuppliersAndReceivingSeeder::class];
    }

    /** **Validates: Requirements 3.11, 9.13** */
    #[DataProvider('inventorySeeders')]
    public function test_inventory_seeders_create_unique_synchronized_aggregates_and_batches(string $seeder): void
    {
        $this->seed($seeder);

        self::assertGreaterThan(0, InventoryItem::count());
        self::assertGreaterThan(0, InventoryBatch::count());
        self::assertSame(
            InventoryItem::count(),
            DB::table('inventory_items')->select(['pharmacy_id', 'medicine_id'])->distinct()->count()
        );
        self::assertSame(
            InventoryBatch::count(),
            DB::table('inventory_batches')->select(['inventory_item_id', 'identity_key'])->distinct()->count()
        );

        InventoryItem::query()->with('batches')->each(function (InventoryItem $aggregate): void {
            $available = $aggregate->batches
                ->filter(fn (InventoryBatch $batch): bool => $batch->current_quantity > 0
                    && ($batch->expiry_date === null || $batch->expiry_date->isToday() || $batch->expiry_date->isFuture()))
                ->sum('current_quantity');
            self::assertSame($available, $aggregate->stockQuantity);
            self::assertNotEmpty($aggregate->batches);
        });

        self::assertSame(InventoryBatch::count(), StockMovement::where('type', 'receipt')->count());
    }
}
