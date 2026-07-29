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
        $pharmacy = $this->pharmacy();
        $inventory = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $messageCount = Message::where('pharmacy_id', $pharmacy->id)->count();
        $unreadCount = Message::where('pharmacy_id', $pharmacy->id)
            ->where('is_read', false)
            ->count();

        $inventoryCount = $inventory->count();
        $inStockCount = $inventory->where('stockQuantity', '>', 0)->count();

        return view('pharmacy.dashboard', compact(
            'pharmacy',
            'inventory',
            'inventoryCount',
            'inStockCount',
            'messageCount',
            'unreadCount'
        ));
    }

    public function inventory(): View
    {
        $pharmacy = $this->pharmacy();
        $inventory = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pharmacy.inventory', compact('inventory'));
    }

    public function updateInventory(Request $request)
    {
        $pharmacy = $this->pharmacy();

        $request->validate([
            'stock' => 'array',
            'price' => 'array',
            'stock.*' => 'integer|min:0',
            'price.*' => 'numeric|min:0',
        ]);

        $stock = $request->input('stock', []);
        $price = $request->input('price', []);

        foreach ($stock as $itemId => $quantity) {
            $item = InventoryItem::where('id', $itemId)
                ->where('pharmacy_id', $pharmacy->id)
                ->first();

            if (!$item) {
                continue;
            }

            $item->update([
                'stockQuantity' => $quantity,
                'price' => $price[$itemId] ?? $item->price,
            ]);
        }

        return back()->with('success', 'Inventory updated successfully.');
    }

    public function messages(): View
    {
        $pharmacy = $this->pharmacy();
        $messages = Message::with('consumer')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pharmacy.messages', compact('messages'));
    }

    public function replyMessage(Request $request, Message $message)
    {
        $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $pharmacy = $this->pharmacy();
        abort_if($message->pharmacy_id !== $pharmacy->id, 403);

        $message->reply = $request->reply;
        $message->replied_at = now();
        $message->is_read = true;
        $message->save();

        return back()->with('success', 'Reply sent successfully!');
    }

    public function markRead(Message $message)
    {
        $pharmacy = $this->pharmacy();
        abort_if($message->pharmacy_id !== $pharmacy->id, 403);

        $message->is_read = true;
        $message->save();

        return back()->with('success', 'Message marked as read.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'medicine_id'    => 'required|exists:medicines,id',
            'stock_quantity' => 'required|integer|min:0',
            'price'          => 'required|numeric|min:0',
        ]);

        $pharmacy = $this->pharmacy();

        $existing = InventoryItem::where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $request->medicine_id)
            ->first();

        if ($existing) {
            return back()->with('error', 'This medicine already exists in inventory. Please update it instead.');
        }

        InventoryItem::create([
            'pharmacy_id'    => $pharmacy->id,
            'medicine_id'    => $request->medicine_id,
            'stockQuantity'  => $request->stock_quantity,
            'price'          => $request->price,
        ]);

        return back()->with('success', 'Medicine added to inventory.');
    }

    public function update(Request $request, InventoryItem $item)
    {
        abort_if($item->pharmacy_id !== $this->pharmacy()->id, 403);

        $request->validate([
            'stock_quantity' => 'required|integer|min:0',
            'price'          => 'required|numeric|min:0',
        ]);

        $item->update([
            'stockQuantity' => $request->stock_quantity,
            'price'         => $request->price,
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
