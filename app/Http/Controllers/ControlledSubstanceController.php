<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\BatchStockService;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Exceptions\InsufficientAvailableStock;
use App\Domain\Inventory\Exceptions\UntraceableStockIncrease;
use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\ControlledSubstanceLog;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ControlledSubstanceController extends Controller
{
    public function index(Request $request, InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $inventoryQuery = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->id);
        $aggregateQuery->withProjections($inventoryQuery);
        $controlledItems = $inventoryQuery->get()->filter(fn (InventoryItem $item) => $item->is_controlled);

        $action = (string) $request->query('action', '');
        $logsQuery = ControlledSubstanceLog::query()
            ->with(['inventoryItem.medicine', 'user'])
            ->whereHas('inventoryItem', fn ($query) => $query->where('pharmacy_id', $pharmacy->id))
            ->orderByDesc('logged_at');

        if ($action !== '') {
            $logsQuery->where('action', $action);
        }

        $logs = $logsQuery->get();
        $actions = ControlledSubstanceLog::query()
            ->whereHas('inventoryItem', fn ($query) => $query->where('pharmacy_id', $pharmacy->id))
            ->pluck('action')
            ->unique()
            ->values()
            ->all();

        return view('pharmacy.controlled_substances_index', compact(
            'pharmacy',
            'controlledItems',
            'logs',
            'actions',
            'action',
        ));
    }

    public function create(InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $query = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->id);
        $aggregateQuery->withProjections($query);
        $controlledItems = $query->get()
            ->filter(fn (InventoryItem $item) => $item->is_controlled)
            ->values();

        return view('pharmacy.controlled_substance_log', compact('pharmacy', 'controlledItems'));
    }

    public function store(Request $request, BatchStockService $stockService)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'action' => ['required', 'in:dispensed,wastage,transferred,adjustment'],
            'quantity' => ['required', 'integer', 'min:0'],
            'patient_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['action'] !== 'adjustment' && (int) $data['quantity'] < 1) {
            return redirect()->back()
                ->withErrors(['quantity' => 'Quantity must be at least one for this action.'])
                ->withInput();
        }

        $item = InventoryItem::query()
            ->with('medicine')
            ->whereKey($data['inventory_item_id'])
            ->where('pharmacy_id', $pharmacy->id)
            ->firstOrFail();

        if (! $item->is_controlled) {
            return redirect()->back()
                ->withErrors(['inventory_item_id' => 'Selected item is not a controlled substance.'])
                ->withInput();
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        if (! empty($data['patient_reference'])) {
            $notes = 'Ref: '.$data['patient_reference'].($notes !== '' ? ' | '.$notes : '');
        }

        try {
            DB::transaction(function () use ($request, $stockService, $item, $data, $notes): void {
                $operationId = (string) Str::uuid();
                $log = ControlledSubstanceLog::query()->create([
                    'inventory_item_id' => $item->id,
                    'user_id' => $request->user()->id,
                    'action' => $data['action'],
                    'quantity' => (int) $data['quantity'],
                    'notes' => $notes !== '' ? $notes : null,
                    'logged_at' => now(),
                    'operation_id' => $operationId,
                ]);
                $context = new StockOperationContext(
                    type: $data['action'] === 'adjustment' ? 'fefo_adjustment' : 'fefo_decrease',
                    actorId: $request->user()->id,
                    reason: ucfirst($data['action']).' — controlled substance log',
                    referenceType: 'controlled_substance_log',
                    referenceId: $log->id,
                    receivedReference: $data['patient_reference'] ?? null,
                    operationId: $operationId,
                );

                if ($data['action'] === 'adjustment') {
                    $stockService->setAvailableQuantity($item, (int) $data['quantity'], $context);
                } else {
                    $stockService->decreaseFefo($item, (int) $data['quantity'], $context);
                }
            });
        } catch (InsufficientAvailableStock $exception) {
            return redirect()->back()
                ->withErrors(['quantity' => "Quantity exceeds available stock ({$exception->available})."])
                ->withInput();
        } catch (UntraceableStockIncrease $exception) {
            return redirect()->back()
                ->withErrors(['quantity' => 'An adjustment cannot increase aggregate stock without a batch. Use Add Stock / Receive Delivery.'])
                ->withInput();
        }

        return redirect()
            ->route('pharmacy.controlled-substances.index')
            ->with('success', 'Controlled-substance entry saved. Stock was deducted from batches in FEFO order.');
    }
}
