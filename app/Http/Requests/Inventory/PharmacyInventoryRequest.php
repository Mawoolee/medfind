<?php

namespace App\Http\Requests\Inventory;

use App\Http\Resolvers\PharmacyInventoryRecordResolver;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use Illuminate\Foundation\Http\FormRequest;

abstract class PharmacyInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPharmacy() === true;
    }

    public function pharmacy(): Pharmacy
    {
        return $this->resolver()->pharmacy($this->user());
    }

    public function aggregate(): InventoryItem
    {
        return $this->resolver()->aggregate($this->user(), $this->routeRecord([
            'inventoryItem',
            'inventory_item',
            'aggregate',
            'id',
        ]));
    }

    public function batch(): InventoryBatch
    {
        $aggregate = $this->routeRecordOrNull(['inventoryItem', 'inventory_item', 'aggregate']);

        return $this->resolver()->batch(
            $this->user(),
            $this->routeRecord(['batch', 'inventoryBatch', 'inventory_batch', 'id']),
            $aggregate,
        );
    }

    protected function resolver(): PharmacyInventoryRecordResolver
    {
        return app(PharmacyInventoryRecordResolver::class);
    }

    /**
     * @param  list<string>  $names
     */
    private function routeRecord(array $names): mixed
    {
        $record = $this->routeRecordOrNull($names);

        abort_if($record === null || $record === '', 404);

        return $record;
    }

    /**
     * @param  list<string>  $names
     */
    private function routeRecordOrNull(array $names): mixed
    {
        foreach ($names as $name) {
            if (($record = $this->route($name)) !== null) {
                return $record;
            }
        }

        return null;
    }
}
