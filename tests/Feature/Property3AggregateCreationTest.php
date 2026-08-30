<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Services\MedicineMasterService;
use Eris\Generators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PropertyTestCase;

final class Property3AggregateCreationTest extends PropertyTestCase
{
    use RefreshDatabase;

    protected function shouldSeed(): bool
    {
        return false;
    }

    /** **Validates: Requirements 2.4** */
    public function test_aggregate_creation_is_idempotent(): void
    {
        // Feature: pharmacy-medicine-batch-stock-management, Property 3: Aggregate creation is idempotent
        $this->forAll(
            Generators::choose(1, 8),
            Generators::vector(8, Generators::choose(0, 1_000)),
        )->then(function (int $invocationCount, array $parLevels): void {
            $pharmacy = Pharmacy::factory()->create();
            $medicine = Medicine::factory()->create();
            $service = app(MedicineMasterService::class);
            $aggregateIds = [];

            for ($invocation = 0; $invocation < $invocationCount; $invocation++) {
                $aggregate = $service->createForPharmacy($pharmacy, [
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medicine->medicine_name,
                ], $parLevels[$invocation]);

                $aggregateIds[] = $aggregate->id;
            }

            self::assertCount(1, array_unique($aggregateIds));
            self::assertSame(1, InventoryItem::query()
                ->where('pharmacy_id', $pharmacy->id)
                ->where('medicine_id', $medicine->id)
                ->count());
        });
    }
}
