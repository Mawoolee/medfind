<?php

namespace Tests\Feature;

use App\Domain\Inventory\BasicSaleService;
use App\Domain\Inventory\BatchIdentity;
use App\Domain\Inventory\Exceptions\SaleLineInsufficientStock;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Eris\Generators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PropertyTestCase;

final class Property21BasicSaleAtomicFefoTest extends PropertyTestCase
{
    use RefreshDatabase;

    protected function shouldSeed(): bool
    {
        return false;
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** **Validates: Requirements 14.3-14.8** */
    public function test_basic_sales_are_atomic_fefo_operations(): void
    {
        // Feature: pharmacy-medicine-batch-stock-management, Property 21: Basic sales are atomic FEFO operations
        CarbonImmutable::setTestNow('2030-06-15 12:00:00');
        $actor = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($actor)->create();
        $actor->update(['pharmacy_id' => $pharmacy->id]);
        $firstAggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => Medicine::factory()->create(['medicine_name' => 'Property First'])->id,
            'price' => '10.00',
        ]);
        $secondAggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => Medicine::factory()->create(['medicine_name' => 'Property Second'])->id,
            'price' => '10.00',
        ]);
        $iteration = 0;

        $this->forAll(
            Generators::choose(1, 20),
            Generators::choose(0, 20),
            Generators::choose(1, 20),
            Generators::choose(0, 20),
            Generators::choose(0, 1),
        )->then(function (
            int $firstRequested,
            int $firstSurplus,
            int $secondRequested,
            int $secondSurplus,
            int $makeInsufficient,
        ) use (
            $pharmacy,
            $actor,
            $firstAggregate,
            $secondAggregate,
            &$iteration,
        ): void {
            $iteration++;
            DB::table('stock_movements')->delete();
            DB::table('inventory_audits')->delete();
            DB::table('inventory_batches')->delete();

            $firstAvailable = $firstRequested + $firstSurplus;
            $secondAvailable = $makeInsufficient === 1
                ? $secondRequested - 1
                : $secondRequested + $secondSurplus;
            DB::table('inventory_items')->where('id', $firstAggregate->id)->update([
                'stockQuantity' => $firstAvailable,
                'updated_at' => now(),
            ]);
            DB::table('inventory_items')->where('id', $secondAggregate->id)->update([
                'stockQuantity' => $secondAvailable,
                'updated_at' => now(),
            ]);
            $firstBatchQuantities = $this->insertFefoPair($firstAggregate, $firstAvailable, "P21-A-{$iteration}");
            $secondBatchQuantities = $this->insertFefoPair($secondAggregate, $secondAvailable, "P21-B-{$iteration}");
            $beforeBatches = DB::table('inventory_batches')->orderBy('id')->pluck('current_quantity', 'id')->all();

            if ($makeInsufficient === 1) {
                try {
                    app(BasicSaleService::class)->record($pharmacy, $actor, [
                        ['inventory_item_id' => $firstAggregate->id, 'quantity' => $firstRequested],
                        ['inventory_item_id' => $secondAggregate->id, 'quantity' => $secondRequested],
                    ]);
                    self::fail('An insufficient generated line must reject the complete sale.');
                } catch (SaleLineInsufficientStock $exception) {
                    self::assertSame(1, $exception->lineIndex);
                    self::assertSame($secondRequested, $exception->requested);
                    self::assertSame($secondAvailable, $exception->available);
                }

                self::assertSame(
                    $beforeBatches,
                    DB::table('inventory_batches')->orderBy('id')->pluck('current_quantity', 'id')->all(),
                );
                self::assertSame($firstAvailable, (int) $firstAggregate->fresh()->stockQuantity);
                self::assertSame($secondAvailable, (int) $secondAggregate->fresh()->stockQuantity);
                self::assertSame(0, DB::table('stock_movements')->count());
                self::assertSame(0, DB::table('inventory_audits')->count());

                return;
            }

            $result = app(BasicSaleService::class)->record($pharmacy, $actor, [
                ['inventory_item_id' => $firstAggregate->id, 'quantity' => $firstRequested],
                ['inventory_item_id' => $secondAggregate->id, 'quantity' => $secondRequested],
            ]);

            self::assertSame($firstAvailable - $firstRequested, (int) $firstAggregate->fresh()->stockQuantity);
            self::assertSame($secondAvailable - $secondRequested, (int) $secondAggregate->fresh()->stockQuantity);
            self::assertSame(
                $this->expectedFefoRemainders($firstBatchQuantities, $firstRequested),
                DB::table('inventory_batches')
                    ->where('inventory_item_id', $firstAggregate->id)
                    ->orderBy('expiry_date')
                    ->pluck('current_quantity')
                    ->map(static fn (mixed $quantity): int => (int) $quantity)
                    ->all(),
            );
            self::assertSame(
                $this->expectedFefoRemainders($secondBatchQuantities, $secondRequested),
                DB::table('inventory_batches')
                    ->where('inventory_item_id', $secondAggregate->id)
                    ->orderBy('expiry_date')
                    ->pluck('current_quantity')
                    ->map(static fn (mixed $quantity): int => (int) $quantity)
                    ->all(),
            );

            $movements = DB::table('stock_movements')->get();
            $audits = DB::table('inventory_audits')->get();
            self::assertNotEmpty($movements);
            self::assertCount(2, $audits);
            self::assertSame(-$firstRequested, (int) $movements
                ->where('inventory_item_id', $firstAggregate->id)->sum('quantity_delta'));
            self::assertSame(-$secondRequested, (int) $movements
                ->where('inventory_item_id', $secondAggregate->id)->sum('quantity_delta'));
            self::assertTrue($movements->every(fn (object $movement): bool => $movement->operation_id === $result->operationId
                && $movement->received_reference === $result->saleReference
                && $movement->reference_id === $result->saleReference
                && (int) $movement->user_id === $actor->id
            ));
            self::assertTrue($audits->every(fn (object $audit): bool => $audit->operation_id === $result->operationId
                && (int) $audit->user_id === $actor->id
                && str_contains((string) $audit->notes, $result->saleReference)
            ));
        });
    }

    /** @return array{0: int, 1: int} */
    private function insertFefoPair(InventoryItem $aggregate, int $available, string $prefix): array
    {
        $firstQuantity = (int) ceil($available / 2);
        $secondQuantity = $available - $firstQuantity;
        $now = now()->toDateTimeString();

        foreach ([
            [$prefix.'-FIRST', $firstQuantity, '2030-07-01'],
            [$prefix.'-SECOND', $secondQuantity, '2030-08-01'],
        ] as [$batchNumber, $quantity, $expiryDate]) {
            DB::table('inventory_batches')->insert([
                'inventory_item_id' => $aggregate->id,
                'legacy_source_inventory_item_id' => null,
                'batch_number' => $batchNumber,
                'lot_number' => null,
                'identity_key' => BatchIdentity::key($batchNumber, null),
                'quantity_received' => $quantity,
                'current_quantity' => $quantity,
                'price' => '10.00',
                'supplier_id' => null,
                'supplier_name' => null,
                'expiry_date' => $expiryDate,
                'cold_chain' => false,
                'received_date' => '2030-05-01',
                'received_reference' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [$firstQuantity, $secondQuantity];
    }

    /**
     * @param  array{0: int, 1: int}  $batchQuantities
     * @return array{0: int, 1: int}
     */
    private function expectedFefoRemainders(array $batchQuantities, int $requested): array
    {
        $remaining = $requested;

        return array_map(static function (int $quantity) use (&$remaining): int {
            $allocated = min($quantity, $remaining);
            $remaining -= $allocated;

            return $quantity - $allocated;
        }, $batchQuantities);
    }
}
