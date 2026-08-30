<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\BatchStockService;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Exceptions\UntraceableStockIncrease;
use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\CycleCount;
use App\Models\CycleCountItem;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CycleCountController extends Controller
{
    public function index()
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $counts = CycleCount::query()
            ->with(['items', 'conductedBy'])
            ->where('pharmacy_id', $pharmacy->id)
            ->orderByDesc('created_at')
            ->get();

        return view('pharmacy.cycle_counts_index', compact('pharmacy', 'counts'));
    }

    public function create(InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $query = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderByDesc('updated_at');
        $aggregateQuery->withProjections($query);
        $inventory = $query->get();

        return view('pharmacy.cycle_counts_create', compact('pharmacy', 'inventory'));
    }

    public function store(Request $request, InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'distinct', 'exists:inventory_items,id'],
        ]);
        $ids = array_map('intval', $data['items']);
        $query = InventoryItem::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->whereKey($ids);
        $aggregateQuery->withAvailableStock($query);
        $inventory = $query->get()->keyBy('id');

        if ($inventory->count() !== count($ids)) {
            return redirect()->back()
                ->withErrors(['items' => 'One or more selected medicines do not belong to this pharmacy.'])
                ->withInput();
        }

        $count = DB::transaction(function () use ($request, $data, $pharmacy, $ids, $inventory): CycleCount {
            $cycle = CycleCount::query()->create([
                'pharmacy_id' => $pharmacy->id,
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
                'scheduled_at' => ! empty($data['scheduled_at']) ? $data['scheduled_at'] : now(),
                'conducted_by' => $request->user()->id,
            ]);

            foreach ($ids as $inventoryId) {
                CycleCountItem::query()->create([
                    'cycle_count_id' => $cycle->id,
                    'inventory_item_id' => $inventoryId,
                    'expected_quantity' => (int) $inventory[$inventoryId]->available_stock,
                ]);
            }

            return $cycle;
        });

        return redirect()
            ->route('pharmacy.cycle-counts.show', $count->id)
            ->with('success', 'Cycle count created. Enter the counted quantities.');
    }

    public function show(int|string $id)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $count = CycleCount::query()
            ->with(['items.inventoryItem.medicine', 'conductedBy'])
            ->whereKey($id)
            ->where('pharmacy_id', $pharmacy->id)
            ->firstOrFail();

        return view('pharmacy.cycle_counts_show', compact('pharmacy', 'count'));
    }

    public function complete(Request $request, int|string $id, BatchStockService $stockService)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $count = CycleCount::query()
            ->with(['items.inventoryItem.medicine'])
            ->whereKey($id)
            ->where('pharmacy_id', $pharmacy->id)
            ->whereNull('completed_at')
            ->firstOrFail();
        $data = $request->validate([
            'counted' => ['required', 'array'],
            'counted.*' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $countItems = $count->items->keyBy('id');

        foreach (array_keys($data['counted']) as $countItemId) {
            if (! $countItems->has((int) $countItemId)) {
                abort(404);
            }
        }

        $failedMedicine = null;
        try {
            DB::transaction(function () use ($request, $data, $count, $countItems, $stockService, &$failedMedicine): void {
                foreach ($data['counted'] as $countItemId => $quantity) {
                    /** @var CycleCountItem $countItem */
                    $countItem = $countItems[(int) $countItemId];
                    $failedMedicine = $countItem->inventoryItem?->medicine?->medicine_name;
                    $countItem->update([
                        'counted_quantity' => (int) $quantity,
                        'notes' => $data['notes'] ?? null,
                    ]);
                    $stockService->setAvailableQuantity(
                        $countItem->inventoryItem,
                        (int) $quantity,
                        new StockOperationContext(
                            type: 'cycle_count_fefo_adjustment',
                            actorId: $request->user()->id,
                            reason: 'Cycle count adjustment: '.$count->name,
                            referenceType: 'cycle_count_item',
                            referenceId: $countItem->id,
                            operationId: (string) Str::uuid(),
                        ),
                    );
                }

                $count->update(['completed_at' => now()]);
            });
        } catch (UntraceableStockIncrease $exception) {
            return redirect()->back()
                ->withErrors(['counted' => "{$failedMedicine} was counted above its current available stock. Add/correct a specific batch before completing the cycle count."])
                ->withInput();
        }

        return redirect()
            ->route('pharmacy.cycle-counts.index')
            ->with('success', 'Cycle count completed. Decreases were applied to batches in FEFO order.');
    }
}
