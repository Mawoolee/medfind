<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\BatchStockService;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Exceptions\ColdChainRequired;
use App\Domain\Inventory\Exceptions\DuplicateBatchIdentity;
use App\Http\Requests\Inventory\ReceiveInventoryRequest;
use App\Models\ControlledSubstanceLog;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{
    public function create(Request $request)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $selectedInventoryId = $request->integer('inventory_item_id') ?: null;
        if ($selectedInventoryId !== null) {
            InventoryItem::query()
                ->whereKey($selectedInventoryId)
                ->where('pharmacy_id', $pharmacy->id)
                ->firstOrFail();
        }

        $suppliers = Supplier::query()->orderBy('name')->get();
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

        return view('pharmacy.receiving_create', compact(
            'pharmacy',
            'suppliers',
            'inventory',
            'selectedInventoryId',
        ));
    }

    public function store(ReceiveInventoryRequest $request, BatchStockService $stockService)
    {
        $processed = 0;
        $failedIndex = 0;

        try {
            DB::transaction(function () use ($request, $stockService, &$processed, &$failedIndex): void {
                $items = $request->validated('items');

                foreach (array_keys($items) as $index) {
                    $failedIndex = (int) $index;
                    $aggregate = InventoryItem::query()
                        ->with('medicine')
                        ->whereKey($items[$index]['inventory_item_id'])
                        ->where('pharmacy_id', $request->pharmacy()->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $receipt = $request->receiptData((int) $index);
                    $context = new StockOperationContext(
                        type: 'receipt',
                        actorId: $request->user()->id,
                        reason: 'Received stock delivery',
                        referenceType: 'purchase_order',
                        referenceId: $receipt->receivedReference,
                        receivedReference: $receipt->receivedReference,
                    );
                    $result = $stockService->receive($aggregate, $receipt, $context);

                    if ($result->aggregate->is_controlled) {
                        ControlledSubstanceLog::query()->create([
                            'inventory_item_id' => $result->aggregate->id,
                            'user_id' => $request->user()->id,
                            'action' => 'received',
                            'quantity' => $receipt->quantityReceived,
                            'notes' => 'Received batch '.$receipt->batchNumber
                                .($receipt->receivedReference ? ' (Reference: '.$receipt->receivedReference.')' : ''),
                            'logged_at' => now(),
                            'operation_id' => $result->operationId,
                        ]);
                    }

                    $processed++;
                }
            });
        } catch (DuplicateBatchIdentity $exception) {
            return redirect()->back()
                ->withErrors(["items.{$failedIndex}.batch_number" => $exception->getMessage()])
                ->withInput();
        } catch (ColdChainRequired $exception) {
            return redirect()->back()
                ->withErrors(["items.{$failedIndex}.cold_chain" => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('pharmacy.inventory')
            ->with('success', "Shipment processed: {$processed} batch(es) received. Previous batches were preserved.");
    }
}
