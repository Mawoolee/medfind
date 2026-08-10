<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\InventoryItem;
use App\Models\ControlledSubstanceLog;
use Illuminate\Http\Request;

class ControlledSubstanceController extends Controller
{
    public function index(Request $request)
    {

        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        // All controlled-substance inventory items for this pharmacy.
        $controlledItems = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->get()
            ->filter(function ($item) {
                return $item->is_controlled;
            });

        // Logbook entries.
        $action = $request->query('action', '');
        $logsQuery = ControlledSubstanceLog::with(['inventoryItem.medicine', 'user'])
            ->whereHas('inventoryItem', function ($q) use ($pharmacy) {
                $q->where('pharmacy_id', $pharmacy->id);
            })
            ->orderBy('logged_at', 'desc');

        if (!empty($action)) {
            $logsQuery->where('action', $action);
        }

        $logs = $logsQuery->get();

        $actions = ControlledSubstanceLog::whereHas('inventoryItem', function ($q) use ($pharmacy) {
            $q->where('pharmacy_id', $pharmacy->id);
        })->pluck('action')->unique()->values()->toArray();

        return view('pharmacy.controlled_substances_index', compact(
            'pharmacy',
            'controlledItems',
            'logs',
            'actions',
            'action'
        ));
    }

    public function create()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $controlledItems = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->get()
            ->filter(fn($item) => $item->is_controlled)
            ->values();

        return view('pharmacy.controlled_substance_log', compact('pharmacy', 'controlledItems'));
    }

    public function store(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'action'            => 'required|in:dispensed,wastage,transferred,adjustment',
            'quantity'          => 'required|integer|min:1',
            'patient_reference' => 'nullable|string|max:255',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $item = InventoryItem::where('id', $data['inventory_item_id'])
            ->where('pharmacy_id', $pharmacy->id)
            ->firstOrFail();

        if (!$item->is_controlled) {
            return back()->withErrors(['inventory_item_id' => 'Selected item is not a controlled substance.'])->withInput();
        }

        $before = $item->stockQuantity;

        if ($data['action'] === 'adjustment') {
            $item->stockQuantity = $data['quantity'];
        } else {
            // dispensed, wastage, transferred all decrease stock
            if ($data['quantity'] > $item->stockQuantity) {
                return back()->withErrors(['quantity' => 'Quantity exceeds current stock (' . $item->stockQuantity . ').' ])->withInput();
            }
            $item->stockQuantity -= $data['quantity'];
        }

        $item->save();
        $item->recordAudit($before, $item->stockQuantity, ucfirst($data['action']) . ' — controlled substance log');

        $notes = $data['notes'] ?? '';
        if (!empty($data['patient_reference'])) {
            $notes = 'Ref: ' . $data['patient_reference'] . ($notes ? ' | ' . $notes : '');
        }

        ControlledSubstanceLog::create([
            'inventory_item_id' => $item->id,
            'user_id'           => auth()->id(),
            'action'            => $data['action'],
            'quantity'          => $data['quantity'],
            'notes'             => $notes ?: null,
            'logged_at'         => now(),
        ]);

        // Broadcast real-time update
        \App\Events\InventoryUpdated::dispatch(
            $pharmacy->id,
            $item->medicine_id,
            $item->medicine->medicine_name ?? null,
            (int) $item->stockQuantity,
            (float) $item->price,
            (bool) optional($item->medicine)->requiresPrescription
        );

        return redirect()->route('pharmacy.controlled-substances.index')
            ->with('success', 'Controlled substance log entry saved. Stock updated.');
    }
}
