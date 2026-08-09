<?php

namespace App\Http\Controllers;

use App\Models\InventoryAudit;
use App\Models\Pharmacy;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $q      = $request->query('q', '');
        $from   = $request->query('from', '');
        $to     = $request->query('to', '');
        $change = $request->query('change', '');

        $query = InventoryAudit::with(['inventoryItem.medicine', 'user'])
            ->whereHas('inventoryItem', fn($iq) => $iq->where('pharmacy_id', $pharmacy->id));

        if (!empty($q)) {
            $query->whereHas('inventoryItem.medicine', fn($mq) =>
                $mq->where('medicine_name', 'like', "%{$q}%")
            );
        }

        if (!empty($from)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (!empty($to)) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($change === 'increase') {
            $query->whereColumn('after_quantity', '>', 'before_quantity');
        } elseif ($change === 'decrease') {
            $query->whereColumn('after_quantity', '<', 'before_quantity');
        }

        $audits = $query->latest()->paginate(20)->withQueryString();

        // Summary counts (unfiltered for the pharmacy)
        $baseQuery = InventoryAudit::whereHas('inventoryItem', fn($iq) => $iq->where('pharmacy_id', $pharmacy->id));
        $totalCount    = $baseQuery->count();
        $increaseCount = (clone $baseQuery)->whereColumn('after_quantity', '>', 'before_quantity')->count();
        $decreaseCount = (clone $baseQuery)->whereColumn('after_quantity', '<', 'before_quantity')->count();

        return view('pharmacy.audit_log', compact(
            'pharmacy',
            'audits',
            'q', 'from', 'to', 'change',
            'totalCount', 'increaseCount', 'decreaseCount'
        ));
    }
}
