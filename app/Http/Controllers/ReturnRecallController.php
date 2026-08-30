<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\BatchStockService;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Exceptions\InsufficientAvailableStock;
use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\ReturnRecall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnRecallController extends Controller
{
    public function index()
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $records = ReturnRecall::query()
            ->with(['inventoryItem.medicine', 'requestedBy'])
            ->whereHas('inventoryItem', fn ($query) => $query->where('pharmacy_id', $pharmacy->id))
            ->orderByDesc('created_at')
            ->get();

        return view('pharmacy.returns_index', compact('pharmacy', 'records'));
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

        return view('pharmacy.returns_create', compact('pharmacy', 'inventory'));
    }

    public function store(Request $request, BatchStockService $stockService)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'type' => ['required', 'in:return,recall'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
        ]);
        $item = InventoryItem::query()
            ->whereKey($data['inventory_item_id'])
            ->where('pharmacy_id', $pharmacy->id)
            ->firstOrFail();

        try {
            DB::transaction(function () use ($request, $stockService, $data, $item): void {
                $record = ReturnRecall::query()->create([
                    'inventory_item_id' => $item->id,
                    'type' => $data['type'],
                    'quantity' => (int) $data['quantity'],
                    'reason' => $data['reason'] ?? null,
                    'status' => 'pending',
                    'requested_by' => $request->user()->id,
                ]);
                $stockService->decreaseFefo(
                    $item,
                    (int) $data['quantity'],
                    new StockOperationContext(
                        type: 'fefo_decrease',
                        actorId: $request->user()->id,
                        reason: 'Return/Recall ('.$data['type'].')'.(! empty($data['reason']) ? ': '.$data['reason'] : ''),
                        referenceType: 'return_recall',
                        referenceId: $record->id,
                        operationId: (string) Str::uuid(),
                    ),
                );
            });
        } catch (InsufficientAvailableStock $exception) {
            return redirect()->back()
                ->withErrors(['quantity' => "Quantity exceeds available stock ({$exception->available})."])
                ->withInput();
        }

        return redirect()
            ->route('pharmacy.returns.index')
            ->with('success', ucfirst($data['type']).' recorded. Stock was deducted from batches in FEFO order.');
    }

    public function updateStatus(Request $request, int|string $id)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $record = ReturnRecall::query()
            ->whereKey($id)
            ->whereHas('inventoryItem', fn ($query) => $query->where('pharmacy_id', $pharmacy->id))
            ->firstOrFail();
        $data = $request->validate(['status' => ['required', 'in:pending,approved,completed,rejected']]);
        $record->update(['status' => $data['status']]);

        return redirect()->back()->with('success', 'Status updated to '.ucfirst($data['status']).'.');
    }
}
