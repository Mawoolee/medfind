<?php
// app/Http/Controllers/PharmacyDashboardController.php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\InventoryItem;
use App\Models\Message;
use App\Models\SearchLog;
use Illuminate\Http\Request;

class PharmacyDashboardController extends Controller
{
    public function index()
    {
        // Get the pharmacy associated with the logged-in user
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();

        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned to your account.');
        }

        // Get inventory count
        $inventoryCount = InventoryItem::where('pharmacy_id', $pharmacy->id)->count();

        // Get items in stock
        $inStockCount = InventoryItem::where('pharmacy_id', $pharmacy->id)
            ->where('stockQuantity', '>', 0)
            ->count();

        // Get messages count
        $messageCount = Message::where('pharmacy_id', $pharmacy->id)->count();

        // Get unread messages count
        $unreadCount = Message::where('pharmacy_id', $pharmacy->id)
            ->where('is_read', false)
            ->count();

        // Low stock alerts (at or below par level)
        $lowStockItems = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->belowPar()
            ->orderBy('stockQuantity', 'asc')
            ->limit(10)
            ->get();

        // Expired / expiring soon (FEFO)
        $expiringItems = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(90)->endOfDay())
            ->orderBy('expiry_date', 'asc')
            ->limit(10)
            ->get();

        $expiredCount = InventoryItem::where('pharmacy_id', $pharmacy->id)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->startOfDay())
            ->count();

$lowStockCount = $lowStockItems->count();

        // Search tracking stats (per thesis: "track number of searches made for their store daily")
        $searchCountTotal = SearchLog::where('pharmacy_id', $pharmacy->id)->count();
        $searchCountToday = SearchLog::where('pharmacy_id', $pharmacy->id)
            ->whereDate('created_at', today())
            ->count();
        $searchCountWeek = SearchLog::where('pharmacy_id', $pharmacy->id)
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->count();

        // Top searched medicines for this pharmacy (derived from search logs)
        $topSearchQueries = SearchLog::where('pharmacy_id', $pharmacy->id)
            ->whereNotNull('query')
            ->selectRaw('query, COUNT(*) as total')
            ->groupBy('query')
            ->orderBy('total', 'desc')
            ->orderBy('query', 'asc')
            ->limit(5)
            ->get();

        return view('pharmacy.dashboard', compact(
            'pharmacy',
            'inventoryCount',
            'inStockCount',
            'messageCount',
            'unreadCount',
            'lowStockItems',
            'lowStockCount',
            'expiringItems',
            'expiredCount',
            'searchCountTotal',
            'searchCountToday',
            'searchCountWeek',
            'topSearchQueries'
        ));
    }

    public function inventory()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();

        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $inventory = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->get();

        $inventoryMedicineNames = $inventory->pluck('medicine.medicine_name')->filter()->unique()->values()->toArray();

        return view('pharmacy.inventory', compact('pharmacy', 'inventory', 'inventoryMedicineNames'));
    }

    public function updateInventory(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();

        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        // Update individual item
        if ($request->has('update_id')) {
            $item = InventoryItem::where('id', $request->update_id)
                ->where('pharmacy_id', $pharmacy->id)
                ->first();

            if ($item) {
                if ($request->has('stock') && isset($request->stock[$item->id])) {
                    $item->stockQuantity = $request->stock[$item->id];
                }
                if ($request->has('price') && isset($request->price[$item->id])) {
                    $item->price = $request->price[$item->id];
                }
                $item->save();
                return redirect()->back()->with('success', 'Inventory updated successfully!');
            }
        }

        // Update multiple items
        if ($request->has('stock')) {
            foreach ($request->stock as $itemId => $stock) {
                $item = InventoryItem::where('id', $itemId)
                    ->where('pharmacy_id', $pharmacy->id)
                    ->first();

                if ($item) {
                    $item->stockQuantity = $stock;
                    if (isset($request->price[$itemId])) {
                        $item->price = $request->price[$itemId];
                    }
                    $item->save();
                }
            }
            return redirect()->back()->with('success', 'All inventory updated successfully!');
        }

        return redirect()->back()->with('error', 'No changes made.');
    }

public function messages(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();

        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        // Status filter: all | unread | read
        $status = $request->query('status', 'all');
        if (!in_array($status, ['all', 'unread', 'read'])) {
            $status = 'all';
        }

        $query = Message::where('pharmacy_id', $pharmacy->id)->with('consumer');

        if ($status === 'unread') {
            $query->where('is_read', false);
        } elseif ($status === 'read') {
            $query->where('is_read', true);
        }

        $messages = $query->orderBy('created_at', 'desc')->get();

        return view('pharmacy.messages', compact('pharmacy', 'messages', 'status'));
    }

    public function replyMessage(Request $request, $id)
    {
        $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();

        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $message = Message::where('id', $id)
            ->where('pharmacy_id', $pharmacy->id)
            ->first();

        if (!$message) {
            return redirect()->back()->with('error', 'Message not found.');
        }

        $message->reply = $request->reply;
        $message->replied_at = now();
        $message->is_read = true;
        $message->save();

        \App\Events\MessageSent::dispatch(
            $message->id,
            $message->consumer_id,
            $pharmacy->id,
            $request->reply,
            auth()->user()->name,
            'pharmacy_to_consumer',
            $request->reply
        );

        return redirect()->back()->with('success', 'Reply sent successfully!');
    }

    public function markRead($id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();

        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $message = Message::where('id', $id)
            ->where('pharmacy_id', $pharmacy->id)
            ->first();

        if ($message) {
            $message->is_read = true;
            $message->save();
        }

        return redirect()->back()->with('success', 'Message marked as read.');
    }
}