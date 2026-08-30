<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class InventoryBatchController extends Controller
{
    public function index(Request $request, InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $selectedInventoryId = $request->integer('inventory_item_id') ?: null;
        $selectedInventory = null;

        if ($selectedInventoryId !== null) {
            $selectedQuery = InventoryItem::query()
                ->with('medicine')
                ->whereKey($selectedInventoryId)
                ->where('pharmacy_id', $pharmacy->id);
            $aggregateQuery->withProjections($selectedQuery);
            $selectedInventory = $selectedQuery->firstOrFail();
        }

        $q = trim((string) $request->query('q', ''));
        $query = InventoryBatch::query()
            ->with(['inventoryItem.medicine', 'supplier', 'creator'])
            ->whereHas('inventoryItem', fn (Builder $inventory) => $inventory->where('pharmacy_id', $pharmacy->id));

        if ($selectedInventory !== null) {
            $query->where('inventory_item_id', $selectedInventory->id);
        }

        if ($q !== '') {
            $query->where(function (Builder $batch) use ($q): void {
                $batch
                    ->where('batch_number', 'like', "%{$q}%")
                    ->orWhere('lot_number', 'like', "%{$q}%")
                    ->orWhere('supplier_name', 'like', "%{$q}%")
                    ->orWhereHas('inventoryItem.medicine', function (Builder $medicine) use ($q): void {
                        $medicine->where('medicine_name', 'like', "%{$q}%")
                            ->orWhere('brand_name', 'like', "%{$q}%");
                    });
            });
        }

        $batches = $query->fefo()->paginate(20)->withQueryString();
        $inventory = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderBy(
                Medicine::query()
                    ->select('medicine_name')
                    ->whereColumn('medicines.id', 'inventory_items.medicine_id')
                    ->limit(1)
            )
            ->get();

        return view('pharmacy.inventory_batches', compact(
            'pharmacy',
            'batches',
            'inventory',
            'selectedInventory',
            'selectedInventoryId',
            'q',
        ));
    }
}
