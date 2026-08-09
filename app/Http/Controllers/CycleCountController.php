<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\InventoryItem;
use App\Models\CycleCount;
use App\Models\CycleCountItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CycleCountController extends Controller
{
    public function index()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $counts = CycleCount::with(['items', 'conductedBy'])
            ->where('pharmacy_id', $pharmacy->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pharmacy.cycle_counts_index', compact('pharmacy', 'counts'));
    }

    public function create()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $inventory = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pharmacy.cycle_counts_create', compact('pharmacy', 'inventory'));
    }

    public function store(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*' => 'required|distinct|exists:inventory_items,id',
        ]);

        $count = DB::transaction(function () use ($data, $pharmacy) {
            $cycle = CycleCount::create([
                'pharmacy_id' => $pharmacy->id,
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
                'scheduled_at' => !empty($data['scheduled_at']) ? $data['scheduled_at'] : now(),
                'conducted_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $itemId => $params) {
                // $params may be ['id' => x] or the item id directly from checkboxes.
                $invId = is_array($params) ? ($params['id'] ?? $itemId) : $params;
                $inv = InventoryItem::find($invId);
                if (!$inv) continue;
                CycleCountItem::create([
                    'cycle_count_id' => $cycle->id,
                    'inventory_item_id' => $inv->id,
                    'expected_quantity' => $inv->stockQuantity,
                ]);
            }

            return $cycle;
        });

        return redirect()->route('pharmacy.cycle-counts.show', $count->id)
            ->with('success', 'Cycle count created. Enter the counted quantities.');
    }

    public function show($id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $count = CycleCount::with(['items.inventoryItem.medicine', 'conductedBy'])
            ->where('id', $id)
            ->where('pharmacy_id', $pharmacy->id)
            ->firstOrFail();

        return view('pharmacy.cycle_counts_show', compact('pharmacy', 'count'));
    }

    public function complete(Request $request, $id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $count = CycleCount::where('id', $id)->where('pharmacy_id', $pharmacy->id)->firstOrFail();

        $data = $request->validate([
            'counted' => 'required|array',
            'counted.*' => 'integer|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($count, $data) {
            foreach ($data['counted'] as $itemId => $qty) {
                $cci = CycleCountItem::find($itemId);
                if (!$cci) continue;
                $cci->counted_quantity = $qty;
                $cci->notes = $data['notes'] ?? null;
                $cci->save();

                // If a discrepancy exists, sync the actual inventory stock.
                if ($cci->discrepancy != 0) {
                    $inv = $cci->inventoryItem;
                    if ($inv) {
                        $before = $inv->stockQuantity;
                        $inv->stockQuantity = $qty;
                        $inv->save();
                        $inv->recordAudit($before, $qty, 'Cycle count adjustment');
                    }
                }
            }

            $count->completed_at = now();
            $count->save();
        });

        return redirect()->route('pharmacy.cycle-counts.index')
            ->with('success', 'Cycle count completed. Discrepancies adjusted.');
    }
}
