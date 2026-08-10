<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pharmacy;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Supplier;
use App\Models\ControlledSubstanceLog;
use App\Events\InventoryUpdated;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{
    // Show receive shipment UI
    public function create()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) return redirect()->back()->with('error', 'No pharmacy assigned.');

        $suppliers = Supplier::orderBy('name')->get();
        $inventory = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->get();

        return view('pharmacy.receiving_create', compact('pharmacy', 'suppliers', 'inventory'));
    }

    // Process received items
    public function store(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) return redirect()->back()->with('error', 'No pharmacy assigned.');

        $data = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_order' => 'nullable|string|max:255',
            'items' => 'required|array',
            'items.*.medicine_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.batch_number' => 'nullable|string|max:255',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.cold_chain' => 'nullable',
            'items.*.is_controlled' => 'nullable',
            'items.*.category' => 'nullable|string|max:255',
        ]);

        $processed = 0;
        $createdInventoryIds = [];

        DB::transaction(function () use ($data, $pharmacy, &$processed, &$createdInventoryIds) {
            foreach ($data['items'] as $it) {
                if (empty($it['medicine_name'])) continue;

                $medicine = Medicine::firstOrCreate(
                    ['medicine_name' => $it['medicine_name']],
                    [
                        'dosage' => $it['dosage'] ?? '',
                        'manufacturer' => $it['manufacturer'] ?? '',
                        'category' => $it['category'] ?? null,
                        'requiresPrescription' => !empty($it['is_controlled']) ? true : ((bool) optional(Medicine::where('medicine_name', $it['medicine_name'])->first())->requiresPrescription),
                    ]
                );

                // If the medicine already existed, update its category if provided.
                if (!empty($it['category'])) {
                    $medicine->category = $it['category'];
                    $medicine->save();
                }

                $existing = InventoryItem::where('pharmacy_id', $pharmacy->id)
                    ->where('medicine_id', $medicine->id)
                    ->first();

                $before = $existing->stockQuantity ?? 0;
                $increment = intval($it['quantity']);

                $inv = InventoryItem::updateOrCreate(
                    [
                        'pharmacy_id' => $pharmacy->id,
                        'medicine_id' => $medicine->id,
                    ],
                    [
                        'stockQuantity' => $before + $increment,
                        'price' => $it['price'] ?? ($existing->price ?? 0),
                        'batch_number' => $it['batch_number'] ?? ($existing->batch_number ?? null),
                        'expiry_date' => !empty($it['expiry_date']) ? $it['expiry_date'] : ($existing->expiry_date ?? null),
                        'cold_chain' => !empty($it['cold_chain']),
                        'supplier_id' => $data['supplier_id'] ?? ($existing->supplier_id ?? null),
                        'status' => 'available',
                    ]
                );

$after = $inv->stockQuantity;
                $inv->recordAudit($before, $after, 'Received via shipment (PO: ' . ($data['purchase_order'] ?? 'N/A') . ')');

                // Broadcast real-time inventory update to public map & pharmacy channel
                InventoryUpdated::dispatch(
                    $pharmacy->id,
                    $inv->medicine_id,
                    $inv->medicine->medicine_name ?? null,
                    (int) $inv->stockQuantity,
                    (float) $inv->price,
                    (bool) optional($inv->medicine)->requiresPrescription
                );

                // Controlled substance handling: create a separate logbook entry.
                if (!empty($it['is_controlled']) || $inv->is_controlled) {
                    ControlledSubstanceLog::create([
                        'inventory_item_id' => $inv->id,
                        'user_id' => auth()->id(),
                        'action' => 'received',
                        'quantity' => $increment,
                        'notes' => 'Received controlled substance. PO: ' . ($data['purchase_order'] ?? 'N/A') . ', Batch: ' . ($it['batch_number'] ?? 'N/A'),
                        'logged_at' => now(),
                    ]);
                }

                $createdInventoryIds[] = $inv->id;
                $processed++;
            }
        });

        return redirect()->route('pharmacy.inventory')
            ->with('success', "Shipment processed: {$processed} item(s) received successfully.");
    }
}
