<?php

namespace Tests\Feature;

use App\Domain\Inventory\AggregateSynchronizer;
use App\Domain\Inventory\BatchIdentity;
use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Eris\Generators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryPropertyGenerators;
use Tests\Support\PropertyTestCase;

final class Property10NearestValidExpiryTest extends PropertyTestCase
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

    /** **Validates: Requirements 4.9, 5.4** */
    public function test_nearest_valid_expiry_follows_fefo(): void
    {
        // Feature: pharmacy-medicine-batch-stock-management, Property 10: Nearest valid expiry follows FEFO
        $baseDate = CarbonImmutable::parse('2025-06-15 12:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($baseDate);

        $aggregate = InventoryItem::factory()->create([
            'stockQuantity' => 0,
            'price' => '0.00',
            'status' => 'out_of_stock',
            'expiry_date' => null,
        ]);
        $query = app(InventoryAggregateQuery::class);
        $synchronizer = app(AggregateSynchronizer::class);

        $this->forAll(
            Generators::choose(-730, 730),
            InventoryPropertyGenerators::batchVector(),
        )->then(function (int $asOfOffset, array $generatedBatches) use (
            $aggregate,
            $baseDate,
            $query,
            $synchronizer,
        ): void {
            $asOf = $baseDate->addDays($asOfOffset)->startOfDay();
            CarbonImmutable::setTestNow($asOf->setTime(12, 0));

            try {
                $batches = $this->replaceBatches($aggregate, $asOf, $generatedBatches);
                $expectedOrder = $this->referenceFefoOrder($batches);
                $expectedAvailableOrder = array_values(array_filter(
                    $expectedOrder,
                    static fn (array $batch): bool => $batch['quantity'] > 0
                        && ($batch['expiry'] === null || $batch['expiry'] >= $asOf->toDateString()),
                ));
                $expectedNearest = $this->firstEligibleDatedBatch($expectedOrder, $asOf);

                self::assertNotNull($expectedNearest, 'The generator must retain an eligible dated batch.');
                self::assertSame(
                    array_column($expectedOrder, 'id'),
                    $query->batchesInFefoOrder($aggregate)->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
                );
                self::assertSame(
                    array_column($expectedAvailableOrder, 'id'),
                    $query->batchesInFefoOrder($aggregate, true, $asOf)
                        ->pluck('id')
                        ->map(static fn ($id): int => (int) $id)
                        ->all(),
                );

                $projected = $query->withNearestValidExpiry(
                    InventoryItem::query()->whereKey($aggregate->getKey()),
                    $asOf,
                )->firstOrFail();

                self::assertSame(
                    $expectedNearest['expiry'],
                    $projected->nearest_valid_expiry?->toDateString(),
                );

                $synchronized = $synchronizer->synchronizeLocked($aggregate, $asOf);

                self::assertSame(
                    $expectedNearest['expiry'],
                    $synchronized->expiry_date?->toDateString(),
                );
            } finally {
                DB::table('inventory_batches')->where('inventory_item_id', $aggregate->getKey())->delete();
                $aggregate->unsetRelations();
            }
        });
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $generatedBatches
     * @return array<int, array{id: int, quantity: int, expiry: ?string, received: string}>
     */
    private function replaceBatches(
        InventoryItem $aggregate,
        CarbonImmutable $asOf,
        array $generatedBatches,
    ): array {
        DB::table('inventory_batches')->where('inventory_item_id', $aggregate->getKey())->delete();

        $specifications = array_map(
            static function (array $generated) use ($asOf): array {
                [$quantity, $expiryOffset, $seed] = $generated;

                return [
                    'quantity' => $quantity,
                    'expiry' => $seed % 4 === 0
                        ? null
                        : $asOf->addDays($expiryOffset)->toDateString(),
                    'received' => $asOf->subDays($seed % 731)->toDateString(),
                ];
            },
            $generatedBatches,
        );

        $anchorExpiry = $asOf
            ->addDays(abs($generatedBatches[0][1]) % 366)
            ->toDateString();
        $earlyReceipt = $asOf
            ->subDays(366 + ($generatedBatches[0][2] % 365))
            ->toDateString();
        $lateReceipt = $asOf
            ->subDays($generatedBatches[1][2] % 365)
            ->toDateString();

        array_push(
            $specifications,
            [
                'quantity' => 1 + ($generatedBatches[0][0] % 500),
                'expiry' => $anchorExpiry,
                'received' => $lateReceipt,
            ],
            [
                'quantity' => 1 + ($generatedBatches[1][0] % 500),
                'expiry' => $anchorExpiry,
                'received' => $earlyReceipt,
            ],
            [
                'quantity' => 1 + ($generatedBatches[2][0] % 500),
                'expiry' => $anchorExpiry,
                'received' => $earlyReceipt,
            ],
            [
                'quantity' => 1 + ($generatedBatches[3][0] % 500),
                'expiry' => null,
                'received' => $asOf->subDays(1_000)->toDateString(),
            ],
            [
                'quantity' => 1 + ($generatedBatches[4][0] % 500),
                'expiry' => $asOf->subDay()->toDateString(),
                'received' => $asOf->subDays(30)->toDateString(),
            ],
            [
                'quantity' => 0,
                'expiry' => $asOf->toDateString(),
                'received' => $asOf->subDays(30)->toDateString(),
            ],
        );

        $timestamp = CarbonImmutable::now()->toDateTimeString();
        $stored = [];

        foreach ($specifications as $index => $specification) {
            $batchNumber = sprintf('PROPERTY-10-%02d', $index);
            $batchId = DB::table('inventory_batches')->insertGetId([
                'inventory_item_id' => $aggregate->getKey(),
                'legacy_source_inventory_item_id' => null,
                'batch_number' => $batchNumber,
                'lot_number' => null,
                'identity_key' => BatchIdentity::key($batchNumber, null),
                'quantity_received' => max(1, $specification['quantity']),
                'current_quantity' => $specification['quantity'],
                'price' => number_format(($index + 1) / 10, 2, '.', ''),
                'supplier_id' => null,
                'supplier_name' => null,
                'expiry_date' => $specification['expiry'],
                'cold_chain' => false,
                'received_date' => $specification['received'],
                'received_reference' => null,
                'created_by' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $stored[] = [
                'id' => (int) $batchId,
                'quantity' => $specification['quantity'],
                'expiry' => $specification['expiry'],
                'received' => $specification['received'],
            ];
        }

        return $stored;
    }

    /**
     * @param  array<int, array{id: int, quantity: int, expiry: ?string, received: string}>  $batches
     * @return array<int, array{id: int, quantity: int, expiry: ?string, received: string}>
     */
    private function referenceFefoOrder(array $batches): array
    {
        usort($batches, static function (array $left, array $right): int {
            $leftHasNoExpiry = $left['expiry'] === null;
            $rightHasNoExpiry = $right['expiry'] === null;

            if ($leftHasNoExpiry !== $rightHasNoExpiry) {
                return $leftHasNoExpiry <=> $rightHasNoExpiry;
            }

            if (! $leftHasNoExpiry) {
                $expiryComparison = strcmp($left['expiry'], $right['expiry']);

                if ($expiryComparison !== 0) {
                    return $expiryComparison;
                }
            }

            $receivedComparison = strcmp($left['received'], $right['received']);

            return $receivedComparison !== 0
                ? $receivedComparison
                : $left['id'] <=> $right['id'];
        });

        return $batches;
    }

    /**
     * @param  array<int, array{id: int, quantity: int, expiry: ?string, received: string}>  $orderedBatches
     * @return array{id: int, quantity: int, expiry: string, received: string}|null
     */
    private function firstEligibleDatedBatch(array $orderedBatches, CarbonImmutable $asOf): ?array
    {
        foreach ($orderedBatches as $batch) {
            if ($batch['quantity'] > 0
                && $batch['expiry'] !== null
                && $batch['expiry'] >= $asOf->toDateString()) {
                return $batch;
            }
        }

        return null;
    }
}
