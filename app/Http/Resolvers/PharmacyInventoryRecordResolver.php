<?php

namespace App\Http\Resolvers;

use App\Models\ControlledSubstanceLog;
use App\Models\CycleCount;
use App\Models\CycleCountItem;
use App\Models\InventoryAudit;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\ReturnRecall;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class PharmacyInventoryRecordResolver
{
    public function pharmacy(User $user): Pharmacy
    {
        $query = Pharmacy::query();

        if ($user->pharmacy_id !== null) {
            return $query->whereKey($user->pharmacy_id)->firstOrFail();
        }

        return $query->where('user_id', $user->getKey())->firstOrFail();
    }

    public function aggregate(User $user, int|string|InventoryItem $aggregate): InventoryItem
    {
        return InventoryItem::query()
            ->whereKey($this->key($aggregate))
            ->where('pharmacy_id', $this->pharmacy($user)->getKey())
            ->firstOrFail();
    }

    public function batch(
        User $user,
        int|string|InventoryBatch $batch,
        int|string|InventoryItem|null $aggregate = null,
    ): InventoryBatch {
        $query = InventoryBatch::query()
            ->whereKey($this->key($batch))
            ->whereHas('inventoryItem', fn ($query) => $query
                ->where('pharmacy_id', $this->pharmacy($user)->getKey()));

        if ($aggregate !== null) {
            $query->where('inventory_item_id', $this->key($aggregate));
        }

        return $query->firstOrFail();
    }

    public function audit(User $user, int|string|InventoryAudit $audit): InventoryAudit
    {
        return InventoryAudit::query()
            ->whereKey($this->key($audit))
            ->whereHas('inventoryItem', fn ($query) => $query
                ->where('pharmacy_id', $this->pharmacy($user)->getKey()))
            ->firstOrFail();
    }

    public function controlledSubstanceLog(
        User $user,
        int|string|ControlledSubstanceLog $log,
    ): ControlledSubstanceLog {
        return ControlledSubstanceLog::query()
            ->whereKey($this->key($log))
            ->whereHas('inventoryItem', fn ($query) => $query
                ->where('pharmacy_id', $this->pharmacy($user)->getKey()))
            ->firstOrFail();
    }

    public function returnRecall(User $user, int|string|ReturnRecall $returnRecall): ReturnRecall
    {
        return ReturnRecall::query()
            ->whereKey($this->key($returnRecall))
            ->whereHas('inventoryItem', fn ($query) => $query
                ->where('pharmacy_id', $this->pharmacy($user)->getKey()))
            ->firstOrFail();
    }

    public function cycleCount(User $user, int|string|CycleCount $cycleCount): CycleCount
    {
        return CycleCount::query()
            ->whereKey($this->key($cycleCount))
            ->where('pharmacy_id', $this->pharmacy($user)->getKey())
            ->firstOrFail();
    }

    public function cycleCountItem(User $user, int|string|CycleCountItem $item): CycleCountItem
    {
        $pharmacyId = $this->pharmacy($user)->getKey();

        return CycleCountItem::query()
            ->whereKey($this->key($item))
            ->whereHas('cycleCount', fn ($query) => $query->where('pharmacy_id', $pharmacyId))
            ->whereHas('inventoryItem', fn ($query) => $query->where('pharmacy_id', $pharmacyId))
            ->firstOrFail();
    }

    private function key(int|string|Model $record): int|string
    {
        $key = $record instanceof Model ? $record->getKey() : $record;

        if ($key === null || $key === '') {
            throw (new ModelNotFoundException)->setModel($record instanceof Model ? $record::class : Model::class);
        }

        return $key;
    }
}
