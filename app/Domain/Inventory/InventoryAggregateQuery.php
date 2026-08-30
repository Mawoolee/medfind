<?php

namespace App\Domain\Inventory;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class InventoryAggregateQuery
{
    public function withAvailableStock(Builder $query, ?CarbonImmutable $asOf = null): Builder
    {
        $this->ensureBaseColumns($query);

        return $query->addSelect([
            'available_stock' => $this->availableQuantitySubquery($this->asOf($asOf)),
        ]);
    }

    public function withPhysicalStock(Builder $query): Builder
    {
        $this->ensureBaseColumns($query);

        return $query->addSelect([
            'physical_stock' => InventoryBatch::query()
                ->selectRaw('COALESCE(SUM(current_quantity), 0)')
                ->whereColumn('inventory_item_id', 'inventory_items.id')
                ->where('current_quantity', '>', 0),
        ]);
    }

    public function withNearestValidExpiry(Builder $query, ?CarbonImmutable $asOf = null): Builder
    {
        $this->ensureBaseColumns($query);
        $date = $this->asOf($asOf)->toDateString();

        return $query->addSelect([
            'nearest_valid_expiry' => InventoryBatch::query()
                ->select('expiry_date')
                ->whereColumn('inventory_item_id', 'inventory_items.id')
                ->where('current_quantity', '>', 0)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $date)
                ->orderBy('expiry_date')
                ->orderBy('received_date')
                ->orderBy('id')
                ->limit(1),
        ]);
    }

    public function orderByNearestValidExpiry(Builder $query, ?CarbonImmutable $asOf = null): Builder
    {
        $date = $this->asOf($asOf)->toDateString();
        $nearestValidExpiry = InventoryBatch::query()
            ->select('expiry_date')
            ->whereColumn('inventory_item_id', 'inventory_items.id')
            ->where('current_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->orderBy('received_date')
            ->orderBy('id')
            ->limit(1);

        return $query
            ->orderByRaw(
                "CASE WHEN ({$nearestValidExpiry->toSql()}) IS NULL THEN 1 ELSE 0 END",
                $nearestValidExpiry->getBindings()
            )
            ->orderBy('nearest_valid_expiry');
    }

    public function withRepresentativePrice(Builder $query, ?CarbonImmutable $asOf = null): Builder
    {
        $this->ensureBaseColumns($query);
        $date = $this->asOf($asOf)->toDateString();
        $price = InventoryBatch::query()
            ->select('price')
            ->whereColumn('inventory_item_id', 'inventory_items.id')
            ->orderByRaw(
                'CASE WHEN current_quantity > 0 AND (expiry_date IS NULL OR expiry_date >= ?) THEN 0 ELSE 1 END',
                [$date]
            )
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->limit(1);
        $grammar = $query->getQuery()->getGrammar();
        $aggregatePrice = $grammar->wrap($query->getModel()->qualifyColumn('price'));

        return $query->selectRaw(
            "COALESCE(({$price->toSql()}), {$aggregatePrice}) AS representative_price",
            $price->getBindings()
        );
    }

    public function withProjections(Builder $query, ?CarbonImmutable $asOf = null): Builder
    {
        $this->withAvailableStock($query, $asOf);
        $this->withPhysicalStock($query);
        $this->withNearestValidExpiry($query, $asOf);

        return $this->withRepresentativePrice($query, $asOf);
    }

    public function available(Builder $query, ?CarbonImmutable $asOf = null): Builder
    {
        return $this->whereAvailableQuantity($query, '>', 0, $this->asOf($asOf));
    }

    public function belowPar(Builder $query, ?CarbonImmutable $asOf = null): Builder
    {
        $asOf = $this->asOf($asOf);
        $subquery = $this->availableQuantitySubquery($asOf);
        $sql = $subquery->toSql();
        $bindings = $subquery->getBindings();
        $grammar = $query->getQuery()->getGrammar();
        $parLevel = $grammar->wrap($query->getModel()->qualifyColumn('par_level'));

        return $query
            ->whereRaw("({$sql}) > 0", $bindings)
            ->whereRaw("({$sql}) <= {$parLevel}", $bindings)
            ->where('par_level', '>', 0);
    }

    public function outOfStock(Builder $query, ?CarbonImmutable $asOf = null): Builder
    {
        return $this->whereAvailableQuantity($query, '=', 0, $this->asOf($asOf));
    }

    public function expiringWithin(Builder $query, int $days, ?CarbonImmutable $asOf = null): Builder
    {
        if ($days < 0) {
            throw new InvalidArgumentException('Expiry window days must be non-negative.');
        }

        $start = $this->asOf($asOf);
        $end = $start->addDays($days);

        return $query->whereHas('batches', function (Builder $batches) use ($start, $end): void {
            $batches
                ->where('current_quantity', '>', 0)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $start->toDateString())
                ->whereDate('expiry_date', '<=', $end->toDateString());
        });
    }

    public function expiredPhysicalStock(Builder $query, ?CarbonImmutable $asOf = null): Builder
    {
        $date = $this->asOf($asOf)->toDateString();

        return $query->whereHas('batches', function (Builder $batches) use ($date): void {
            $batches
                ->where('current_quantity', '>', 0)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', $date);
        });
    }

    public function batchesInFefoOrder(InventoryItem|int $aggregate, bool $availableOnly = false, ?CarbonImmutable $asOf = null): Builder
    {
        $query = InventoryBatch::query()
            ->where('inventory_item_id', $aggregate instanceof InventoryItem ? $aggregate->getKey() : $aggregate);

        if ($availableOnly) {
            $query->available($this->asOf($asOf));
        }

        return $query->fefo();
    }

    private function availableQuantitySubquery(CarbonImmutable $asOf): Builder
    {
        return InventoryBatch::query()
            ->selectRaw('COALESCE(SUM(current_quantity), 0)')
            ->whereColumn('inventory_item_id', 'inventory_items.id')
            ->where('current_quantity', '>', 0)
            ->where(function (Builder $batches) use ($asOf): void {
                $batches
                    ->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $asOf->toDateString());
            });
    }

    private function whereAvailableQuantity(Builder $query, string $operator, int $quantity, CarbonImmutable $asOf): Builder
    {
        $subquery = $this->availableQuantitySubquery($asOf);

        return $query->whereRaw(
            "({$subquery->toSql()}) {$operator} ?",
            [...$subquery->getBindings(), $quantity]
        );
    }

    private function ensureBaseColumns(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->getModel()->qualifyColumn('*'));
        }
    }

    private function asOf(?CarbonImmutable $asOf): CarbonImmutable
    {
        return ($asOf ?? CarbonImmutable::now())->startOfDay();
    }
}
