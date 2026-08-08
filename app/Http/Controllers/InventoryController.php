<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $q = $request->query('q', '');

        $query = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderBy('created_at', 'desc');

        if (!empty($q)) {
            $query->whereHas('medicine', function($mq) use ($q) {
                $mq->where('medicine_name', 'ilike', "%{$q}%")
                   ->orWhere('manufacturer', 'ilike', "%{$q}%");
            });
        }

        $inventory = $query->paginate(15)->withQueryString();

        $inventoryMedicineNames = $inventory->pluck('medicine')->filter()->map(function($m){ return $m->medicine_name; })->unique()->values()->toArray();

        return view('pharmacy.inventory', compact('pharmacy', 'inventory', 'inventoryMedicineNames', 'q'));
    }

    public function create()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $medicines = Medicine::orderBy('medicine_name')->get();
        return view('pharmacy.inventory_create', compact('pharmacy', 'medicines'));
    }

    public function store(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $data = $request->validate([
            'medicine_id' => 'nullable|exists:medicines,id',
            'medicine_name' => 'nullable|string|max:255',
            'dosage' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'stockQuantity' => 'required|integer|min:0',
        ]);

        if (empty($data['medicine_id']) && empty($data['medicine_name'])) {
            return redirect()->back()->with('error', 'Please select or enter a medicine name.')->withInput();
        }

        if (empty($data['medicine_id'])) {
            $medicine = Medicine::create([
                'medicine_name' => $data['medicine_name'],
                'dosage' => $data['dosage'] ?? '',
                'manufacturer' => $data['manufacturer'] ?? '',
            ]);
        } else {
            $medicine = Medicine::find($data['medicine_id']);
        }

        // create or update inventory item
        $item = InventoryItem::updateOrCreate(
            ['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id],
            ['stockQuantity' => $data['stockQuantity'], 'price' => $data['price'], 'status' => 'available']
        );

        return redirect()->route('pharmacy.inventory')->with('success', 'Inventory item added/updated successfully.');
    }

    public function edit($id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::with('medicine')->where('id', $id)->where('pharmacy_id', $pharmacy->id)->firstOrFail();
        return view('pharmacy.inventory_edit', compact('pharmacy', 'item'));
    }

    public function update(Request $request, $id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::where('id', $id)->where('pharmacy_id', $pharmacy->id)->firstOrFail();

        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'stockQuantity' => 'required|integer|min:0',
        ]);

        $item->price = $data['price'];
        $item->stockQuantity = $data['stockQuantity'];
        $item->save();

        return redirect()->route('pharmacy.inventory')->with('success', 'Inventory item updated.');
    }

    public function destroy($id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::where('id', $id)->where('pharmacy_id', $pharmacy->id)->first();
        if ($item) $item->delete();

        return redirect()->route('pharmacy.inventory')->with('success', 'Inventory item deleted.');
    }
}
