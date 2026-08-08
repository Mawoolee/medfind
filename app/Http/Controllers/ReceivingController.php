<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pharmacy;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Supplier;

class ReceivingController extends Controller
{
    // Show receive shipment UI
    public function create()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) return redirect()->back()->with('error', 'No pharmacy assigned.');

        $suppliers = Supplier::orderBy('name')->get();
        return view('pharmacy.receiving_create', compact('pharmacy', 'suppliers'));
    }

    // Process received items (basic)
    public function store(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) return redirect()->back()->with('error', 'No pharmacy assigned.');

        $data = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'items' => 'required|array',
        ]);

        foreach ($data['items'] as $it) {
            // expected structure: medicine_name, dosage, manufacturer, quantity, price, batch_number, expiry_date, cold_chain
            if (empty($it['medicine_name'])) continue;

            $medicine = Medicine::firstOrCreate(['medicine_name' => $it['medicine_name']], [
                'dosage' => $it['dosage'] ?? '',
                'manufacturer' => $it['manufacturer'] ?? '',
            ]);

            $inv = InventoryItem::updateOrCreate(
                ['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id],
                ['stockQuantity' => (
                    function($existing,$inc){ return $existing + $inc; }
                )( optional(InventoryItem::where('pharmacy_id', $pharmacy->id)->where('medicine_id',$medicine->id)->first())->stockQuantity ?? 0, intval($it['quantity'])),
                'price' => $it['price'] ?? 0,
                'batch_number' => $it['batch_number'] ?? null,
                'expiry_date' => !empty($it['expiry_date']) ? $it['expiry_date'] : null,
                'cold_chain' => !empty($it['cold_chain']) ? boolval($it['cold_chain']) : false,
                'supplier_id' => $data['supplier_id'] ?? null,
            ]);
        }

        return redirect()->route('pharmacy.inventory')->with('success', 'Shipment processed');
    }
}
