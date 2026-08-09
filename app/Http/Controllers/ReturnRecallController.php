<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\InventoryItem;
use App\Models\ReturnRecall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnRecallController extends Controller
{
    public function index()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $records = ReturnRecall::with(['inventoryItem.medicine', 'requestedBy'])
            ->whereHas('inventoryItem', function ($q) use ($pharmacy) {
                $q->where('pharmacy_id', $pharmacy->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pharmacy.returns_index', compact('pharmacy', 'records'));
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

        return view('pharmacy.returns_create', compact('pharmacy', 'inventory'));
    }

    public function store(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'type' => 'required|in:return,recall',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        $item = InventoryItem::where('id', $data['inventory_item_id'])
            ->where('pharmacy_id', $pharmacy->id)
            ->firstOrFail();

        if ($data['quantity'] > $item->stockQuantity) {
            return redirect()->back()->with('error', 'Quantity exceeds current stock.')->withInput();
        }

        DB::transaction(function () use ($data, $item) {
            $rr = ReturnRecall::create([
                'inventory_item_id' => $item->id,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'requested_by' => auth()->id(),
            ]);

            // Decrement stock immediately (pending confirmation).
            $before = $item->stockQuantity;
            $item->stockQuantity -= $data['quantity'];
            $item->save();
            $item->recordAudit($before, $item->stockQuantity, 'Return/Recall (' . $data['type'] . ')');
        });

        return redirect()->route('pharmacy.returns.index')
            ->with('success', (strtoupper($data['type']) === 'RECALL' ? 'Recall' : 'Return') . ' recorded. Stock adjusted.');
    }

    public function updateStatus(Request $request, $id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $record = ReturnRecall::where('id', $id)->first();
        if (!$record || $record->inventoryItem->pharmacy_id != $pharmacy->id) {
            return redirect()->back()->with('error', 'Record not found.');
        }

        $data = $request->validate([
            'status' => 'required|in:pending,approved,completed,rejected',
        ]);

        $record->status = $data['status'];
        $record->save();

        return redirect()->back()->with('success', 'Status updated to ' . ucfirst($data['status']) . '.');
    }
}
