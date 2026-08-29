<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Data\ReconciliationReport;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AggregateSynchronizer
{
    public function __construct(
        private readonly InventoryAggregateQuery $aggregateQuery,
    ) {}

    public function synchronizeLocked(InventoryItem $aggregate, CarbonImmutable $asOf): InventoryItem
    {
        return DB::transaction(function () use ($aggregate, $asOf): InventoryItem {
            $locked = $this->projectedQuery($asOf)
                ->whereKey($aggregate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->applyProjection($locked);

            return $locked;
        });
    }

    public function synchronizeChunk(int $chunkSize, CarbonImmutable $asOf): ReconciliationReport
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Reconciliation chunk size must be at least one.');
        }

        $processed = 0;
        $updated = 0;

        InventoryItem::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($items) use ($asOf, &$processed, &$updated): void {
                $ids = $items->modelKeys();

                DB::transaction(function () use ($ids, $asOf, &$processed, &$updated): void {
                    $aggregates = $this->projectedQuery($asOf)
                        ->whereKey($ids)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($aggregates as $aggregate) {
                        $processed++;

                        if ($this->applyProjection($aggregate)) {
                            $updated++;
                        }
                    }
                });
            });

        return new ReconciliationReport($processed, $updated);
    }

    public static function statusFor(int $availableStock, int $parLevel): string
    {
        return match (true) {
            $availableStock === 0 => 'out_of_stock',
            $parLevel > 0 && $availableStock <= $parLevel => 'low_stock',
            default => 'available',
        };
    }

    private function projectedQuery(CarbonImmutable $asOf): Builder
    {
        return $this->aggregateQuery->withProjections(InventoryItem::query(), $asOf);
    }

    private function applyProjection(InventoryItem $aggregate): bool
    {
        $availableStock = (int) $aggregate->available_stock;
        $representativePrice = $aggregate->representative_price;
        $nearestExpiry = $aggregate->nearest_valid_expiry;
        $targetPrice = number_format(
            max(0, (float) ($representativePrice ?? $aggregate->price)),
            2,
            '.',
            ''
        );
        $projection = [
            'stockQuantity' => $availableStock,
            'status' => self::statusFor($availableStock, (int) $aggregate->par_level),
            'expiry_date' => $nearestExpiry?->toDateString(),
        ];

        if (number_format((float) $aggregate->price, 2, '.', '') !== $targetPrice) {
            $projection['price'] = $targetPrice;
        }

        $aggregate->fill($projection);

        if (! $aggregate->isDirty(['stockQuantity', 'price', 'status', 'expiry_date'])) {
            return false;
        }

        $aggregate->save();

        return true;
    }
}
