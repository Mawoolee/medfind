<?php

namespace Tests\Feature;

use App\Domain\Inventory\AggregateSynchronizer;
use App\Domain\Inventory\BatchIdentity;
use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Eris\Attributes\ErisRepeat;
use Eris\Generators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PropertyTestCase;

#[ErisRepeat(100)]
final class Property9RepresentativePriceTest extends PropertyTestCase
{
    use RefreshDatabase;

    private const BATCH_COUNT = 8;

    protected function shouldSeed(): bool
    {
        return false;
    }

    /** **Validates: Requirements 4.3, 4.4** */
    public function test_representative_price_is_deterministic(): void
    {
        // Feature: pharmacy-medicine-batch-stock-management, Property 9: Representative price is deterministic
        $asOf = CarbonImmutable::parse('2025-06-15')->startOfDay();
        CarbonImmutable::setTestNow($asOf);

        $this->forAll(
            Generators::elements([
                'available-random',
                'available-tie',
                'fallback-random',
                'fallback-tie',
            ]),
            Generators::choose(0, self::BATCH_COUNT - 1),
            Generators::vector(self::BATCH_COUNT, Generators::tuple(
                Generators::choose(0, 50),
                Generators::elements(['expired', 'today', 'future', 'none']),
                Generators::choose(1, 30),
                Generators::choose(0, 30),
                Generators::choose(0, 500_000),
            )),
        )->then(function (string $scenario, int $guaranteedAvailableIndex, array $batchSpecs) use ($asOf): void {
            $aggregate = InventoryItem::factory()->create([
                'stockQuantity' => 999,
                'price' => '54321.99',
            ]);

            if (str_ends_with($scenario, '-tie')) {
                $batchSpecs[self::BATCH_COUNT - 1][4] =
                    ((int) $batchSpecs[self::BATCH_COUNT - 2][4] + 1) % 500_001;
            }

            $batches = [];

            foreach ($batchSpecs as $index => $batchSpec) {
                [$quantity, $expiryKind, $expiryDistance, $receivedAge, $priceCents] = $batchSpec;
                $quantity = (int) $quantity;
                $expiryDate = $this->expiryDate(
                    (string) $expiryKind,
                    (int) $expiryDistance,
                    $asOf,
                );
                $receivedDate = $asOf->subDays((int) $receivedAge)->toDateString();

                if ($scenario === 'available-random' && $index === $guaranteedAvailableIndex) {
                    $quantity = max(1, $quantity);

                    if ($expiryDate !== null && $expiryDate < $asOf->toDateString()) {
                        $expiryDate = $asOf->toDateString();
                    }
                }

                if ($scenario === 'available-tie') {
                    $receivedDate = $asOf->subDays(max(1, (int) $receivedAge))->toDateString();

                    if ($index >= self::BATCH_COUNT - 2) {
                        $quantity = max(1, $quantity);
                        $expiryDate = $index === self::BATCH_COUNT - 2
                            ? null
                            : $asOf->toDateString();
                        $receivedDate = $asOf->toDateString();
                    }
                }

                if (str_starts_with($scenario, 'fallback-')) {
                    if ($index % 2 === 0) {
                        $quantity = 0;
                    } else {
                        $quantity = max(1, $quantity);
                        $expiryDate = $asOf->subDays((int) $expiryDistance)->toDateString();
                    }

                    if ($scenario === 'fallback-tie') {
                        $receivedDate = $asOf->subDays(max(1, (int) $receivedAge))->toDateString();

                        if ($index >= self::BATCH_COUNT - 2) {
                            $receivedDate = $asOf->toDateString();
                        }
                    }
                }

                $batchNumber = sprintf('PROPERTY-9-%d-%d', $aggregate->id, $index);
                $price = $this->priceFromCents((int) $priceCents);
                $batch = InventoryBatch::factory()->create([
                    'inventory_item_id' => $aggregate->id,
                    'batch_number' => $batchNumber,
                    'lot_number' => null,
                    'identity_key' => BatchIdentity::key($batchNumber, null),
                    'quantity_received' => max(1, $quantity),
                    'current_quantity' => $quantity,
                    'price' => $price,
                    'expiry_date' => $expiryDate,
                    'received_date' => $receivedDate,
                ]);

                $batches[] = [
                    'id' => (int) $batch->id,
                    'quantity' => $quantity,
                    'expiry' => $expiryDate,
                    'received' => $receivedDate,
                    'price' => $price,
                ];
            }

            $availableCount = count(array_filter(
                $batches,
                static fn (array $batch): bool => self::isAvailable($batch, $asOf),
            ));

            if (str_starts_with($scenario, 'available-')) {
                self::assertGreaterThan(0, $availableCount);
            } else {
                self::assertSame(0, $availableCount);
            }

            $expectedPrice = self::expectedRepresentativePrice($batches, $asOf);
            $projected = app(InventoryAggregateQuery::class)
                ->withRepresentativePrice(InventoryItem::query()->whereKey($aggregate), $asOf)
                ->firstOrFail();

            self::assertSame(
                $expectedPrice,
                (string) $projected->representative_price,
                "The projected representative price was incorrect for {$scenario}.",
            );

            $synchronized = app(AggregateSynchronizer::class)->synchronizeLocked($aggregate, $asOf);

            self::assertSame(
                $expectedPrice,
                number_format((float) $synchronized->price, 2, '.', ''),
                "The synchronizer selected an incorrect representative price for {$scenario}.",
            );
            self::assertSame(
                $expectedPrice,
                number_format((float) $aggregate->fresh()->price, 2, '.', ''),
                "The representative price was not persisted for {$scenario}.",
            );
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function expiryDate(string $kind, int $distance, CarbonImmutable $asOf): ?string
    {
        return match ($kind) {
            'expired' => $asOf->subDays($distance)->toDateString(),
            'today' => $asOf->toDateString(),
            'future' => $asOf->addDays($distance)->toDateString(),
            'none' => null,
        };
    }

    private function priceFromCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    /**
     * @param  array<int, array{id: int, quantity: int, expiry: ?string, received: string, price: string}>  $batches
     */
    private static function expectedRepresentativePrice(array $batches, CarbonImmutable $asOf): string
    {
        $available = array_values(array_filter(
            $batches,
            static fn (array $batch): bool => self::isAvailable($batch, $asOf),
        ));
        $candidates = $available !== [] ? $available : $batches;

        usort($candidates, static function (array $left, array $right): int {
            $receivedOrder = strcmp($right['received'], $left['received']);

            return $receivedOrder !== 0
                ? $receivedOrder
                : $right['id'] <=> $left['id'];
        });

        return $candidates[0]['price'];
    }

    /**
     * @param  array{id: int, quantity: int, expiry: ?string, received: string, price: string}  $batch
     */
    private static function isAvailable(array $batch, CarbonImmutable $asOf): bool
    {
        return $batch['quantity'] > 0
            && ($batch['expiry'] === null || $batch['expiry'] >= $asOf->toDateString());
    }
}
