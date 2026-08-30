<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Data\ReconciliationReport;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AggregateSynchronizer
{
    /**
     * Operation type recorded for availability changes that no stock operation owns.
     */
    public const RECONCILIATION_TYPE = 'reconciliation';

    /**
     * Audit note recorded for system-attributed reconciliation changes.
     */
    public const RECONCILIATION_REASON = 'System reconciliation: available stock recalculated from authoritative batches (expiry boundary or drift correction).';

    public function __construct(
        private readonly InventoryAggregateQuery $aggregateQuery,
        private readonly StockOperationRecorder $recorder,
    ) {}

    /**
     * Refresh the cached aggregate projections for one locked aggregate.
     *
     * Callers must declare their origin. Stock operations record their own
     * StockMovement and InventoryAudit entries through StockOperationRecorder,
     * so this method stays audit-free for them; standalone reconciliation has
     * no such owner and is audited here.
     */
    public function synchronizeLocked(
        InventoryItem $aggregate,
        CarbonImmutable $asOf,
        SynchronizationOrigin $origin = SynchronizationOrigin::StockOperation,
    ): InventoryItem {
        return DB::transaction(function () use ($aggregate, $asOf, $origin): InventoryItem {
            $locked = $this->projectedQuery($asOf)
                ->whereKey($aggregate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->applyProjection(
                $locked,
                $origin->recordsOwnAudit() ? $this->reconciliationContext() : null,
            );

            return $locked;
        });
    }

    /**
     * Reconcile one aggregate outside any stock operation, recording a
     * system-attributed audit entry when available stock changes.
     */
    public function reconcileLocked(
        InventoryItem $aggregate,
        CarbonImmutable $asOf,
        ?StockOperationContext $context = null,
    ): InventoryItem {
        return DB::transaction(function () use ($aggregate, $asOf, $context): InventoryItem {
            $locked = $this->projectedQuery($asOf)
                ->whereKey($aggregate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->applyProjection($locked, $context ?? $this->reconciliationContext());

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
        $context = $this->reconciliationContext();

        InventoryItem::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($items) use ($asOf, $context, &$processed, &$updated): void {
                $ids = $items->modelKeys();

                DB::transaction(function () use ($ids, $asOf, $context, &$processed, &$updated): void {
                    $aggregates = $this->projectedQuery($asOf)
                        ->whereKey($ids)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($aggregates as $aggregate) {
                        $processed++;

                        if ($this->applyProjection($aggregate, $context)) {
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

    /**
     * @param  StockOperationContext|null  $auditContext  Non-null only when this
     *                                                    synchronization owns the audit for the availability change.
     */
    private function applyProjection(InventoryItem $aggregate, ?StockOperationContext $auditContext): bool
    {
        $beforeAvailableStock = (int) $aggregate->stockQuantity;
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

        if ($auditContext !== null) {
            $this->recorder->recordAudit(
                $aggregate,
                $beforeAvailableStock,
                $availableStock,
                $auditContext,
            );
        }

        return true;
    }

    private function reconciliationContext(): StockOperationContext
    {
        return new StockOperationContext(
            type: self::RECONCILIATION_TYPE,
            actorId: null,
            reason: self::RECONCILIATION_REASON,
        );
    }
}
