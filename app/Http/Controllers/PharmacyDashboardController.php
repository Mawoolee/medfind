<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyDashboardController extends Controller
{
    private function pharmacy()
    {
        return auth()->user()->pharmacy;
    }

    public function index(): View
    {
        $pharmacy  = $this->pharmacy();
        $inventory = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $medicines      = Medicine::orderBy('name')->get();
        $unreadMessages = Message::where('pharmacy_id', $pharmacy->id)
            ->where('sender', 'consumer')
            ->where('is_read', false)
            ->count();

        $stats = [
            'total'     => $inventory->count(),
            'in_stock'  => $inventory->where('status', 'in_stock')->count(),
            'low_stock' => $inventory->where('status', 'low_stock')->count(),
            'out'       => $inventory->where('status', 'out_of_stock')->count(),
            'unread'    => $unreadMessages,
        ];

        return view('pharmacy.dashboard', compact('pharmacy', 'inventory', 'medicines', 'stats'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'medicine_id'    => 'required|exists:medicines,id',
            'dosage'         => 'required|string|max:100',
            'stock_quantity' => 'required|integer|min:0',
            'price'          => 'required|numeric|min:0',
        ]);

        $pharmacy = $this->pharmacy();

        // Prevent duplicate medicine+dosage per pharmacy
        $existing = InventoryItem::where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $request->medicine_id)
            ->where('dosage', $request->dosage)
            ->first();

        if ($existing) {
            return back()->with('error', 'This medicine with that dosage already exists. Please update it instead.');
        }

        InventoryItem::create([
            'pharmacy_id'    => $pharmacy->id,
            'medicine_id'    => $request->medicine_id,
            'dosage'         => $request->dosage,
            'stock_quantity' => $request->stock_quantity,
            'price'          => $request->price,
        ]);

        return back()->with('success', 'Medicine added to inventory.');
    }

    public function update(Request $request, InventoryItem $item)
    {
        // Make sure the item belongs to this pharmacy
        abort_if($item->pharmacy_id !== $this->pharmacy()->id, 403);

        $request->validate([
            'stock_quantity' => 'required|integer|min:0',
            'price'          => 'required|numeric|min:0',
        ]);

        $item->update([
            'stock_quantity' => $request->stock_quantity,
            'price'          => $request->price,
        ]);

        return back()->with('success', 'Stock updated successfully.');
    }

    public function destroy(InventoryItem $item)
    {
        abort_if($item->pharmacy_id !== $this->pharmacy()->id, 403);
        $item->delete();
        return back()->with('success', 'Medicine removed from inventory.');
    }
}
