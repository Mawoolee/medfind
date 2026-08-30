<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryAggregateQuery;
use App\Events\MessageSent;
use App\Models\InventoryItem;
use App\Models\Message;
use App\Models\Pharmacy;
use App\Models\SearchLog;
use App\Services\PrescriptionService;
use Illuminate\Http\Request;

class PharmacyDashboardController extends Controller
{
    public function index(InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned to your account.');
        }

        $inventoryCount = InventoryItem::query()->where('pharmacy_id', $pharmacy->id)->count();
        $inStockCount = $aggregateQuery->available(
            InventoryItem::query()->where('pharmacy_id', $pharmacy->id)
        )->count();

        $messageCount = Message::query()->where('pharmacy_id', $pharmacy->id)->count();
        $unreadCount = Message::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->where('is_read', false)
            ->count();

        $lowStockCount = $aggregateQuery->belowPar(
            InventoryItem::query()->where('pharmacy_id', $pharmacy->id)
        )->count();
        $lowStockQuery = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->id);
        $aggregateQuery->withProjections($lowStockQuery);
        $aggregateQuery->belowPar($lowStockQuery);
        $lowStockItems = $lowStockQuery->orderBy('available_stock')->limit(10)->get();

        $expiringQuery = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->id);
        $aggregateQuery->withProjections($expiringQuery);
        $aggregateQuery->expiringWithin($expiringQuery, 90);
        $expiringItems = $aggregateQuery
            ->orderByNearestValidExpiry($expiringQuery)
            ->limit(10)
            ->get();
        $expiredCount = $aggregateQuery->expiredPhysicalStock(
            InventoryItem::query()->where('pharmacy_id', $pharmacy->id)
        )->count();

        $recentQuery = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderByDesc('updated_at')
            ->limit(5);
        $aggregateQuery->withProjections($recentQuery);
        $recentInventory = $recentQuery->get();

        $searchCountTotal = SearchLog::query()->where('pharmacy_id', $pharmacy->id)->count();
        $searchCountToday = SearchLog::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->whereDate('created_at', today())
            ->count();
        $searchCountWeek = SearchLog::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->count();
        $topSearchQueries = SearchLog::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->whereNotNull('query')
            ->selectRaw('query, COUNT(*) as total')
            ->groupBy('query')
            ->orderByDesc('total')
            ->orderBy('query')
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
            'recentInventory',
            'searchCountTotal',
            'searchCountToday',
            'searchCountWeek',
            'topSearchQueries',
        ));
    }

    public function inventory()
    {
        return redirect()->route('pharmacy.inventory');
    }

    public function updateInventory()
    {
        return redirect()->back()->with(
            'error',
            'Direct total-stock editing is disabled. Use Add Stock for increases and an authorized stock operation for decreases.'
        );
    }

    public function messages(Request $request)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'unread', 'read'], true)) {
            $status = 'all';
        }

        $query = Message::query()->where('pharmacy_id', $pharmacy->id)->with('consumer');
        if ($status === 'unread') {
            $query->where('is_read', false);
        } elseif ($status === 'read') {
            $query->where('is_read', true);
        }

        $messages = $query->orderByDesc('created_at')->get();

        return view('pharmacy.messages', compact('pharmacy', 'messages', 'status'));
    }

    public function replyMessage(Request $request, int|string $id)
    {
        $request->validate([
            'reply' => ['required', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv', 'max:10240'],
        ]);

        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $message = Message::query()
            ->whereKey($id)
            ->where('pharmacy_id', $pharmacy->id)
            ->first();
        if (! $message) {
            return redirect()->back()->with('error', 'Message not found.');
        }

        $newMessage = Message::query()->create([
            'consumer_id' => $message->consumer_id,
            'pharmacy_id' => $pharmacy->id,
            'sender' => 'pharmacy',
            'message' => $request->string('reply')->toString(),
            'is_read' => true,
        ]);

        if ($request->hasFile('attachments')) {
            $prescriptions = app(PrescriptionService::class);
            $attachmentData = [];
            foreach ($request->file('attachments') as $file) {
                $attachmentData[] = [
                    'path' => $prescriptions->store($file),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
            $newMessage->attachments = $attachmentData;
            $newMessage->save();
        }

        $message->is_read = true;
        $message->save();

        MessageSent::dispatch(
            $newMessage->id,
            $message->consumer_id,
            $pharmacy->id,
            $request->string('reply')->toString(),
            auth()->user()->name,
            'pharmacy_to_consumer',
            $request->string('reply')->toString(),
        );

        return redirect()->back()->with('success', 'Reply sent successfully!');
    }

    public function markRead(int|string $id)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $message = Message::query()
            ->whereKey($id)
            ->where('pharmacy_id', $pharmacy->id)
            ->first();

        if ($message) {
            $message->is_read = true;
            $message->save();
        }

        return redirect()->back()->with('success', 'Message marked as read.');
    }
}
