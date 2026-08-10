<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function index(Request $request)
    {
        $q          = $request->query('q', '');
        $pharmacyId = $request->query('pharmacy_id', '');
        $stock      = $request->query('stock', '');
        $category   = $request->query('category', '');

        $query = InventoryItem::with(['pharmacy', 'medicine'])
            ->whereHas('pharmacy', fn($pq) => $pq->where('status', 'approved'));

        if (!empty($q)) {
            $query->whereHas('medicine', fn($mq) =>
                $mq->where('medicine_name', 'like', "%{$q}%")
                   ->orWhere('dosage', 'like', "%{$q}%")
            );
        }

        if (!empty($pharmacyId)) {
            $query->where('pharmacy_id', $pharmacyId);
        }

        if (!empty($category)) {
            $query->whereHas('medicine', fn($mq) => $mq->where('category', $category));
        }

        switch ($stock) {
            case 'in':
                $query->where('stockQuantity', '>', 0);
                break;
            case 'out':
                $query->where('stockQuantity', '<=', 0);
                break;
            case 'low':
                $query->whereColumn('stockQuantity', '<=', 'par_level')->where('par_level', '>', 0);
                break;
            case 'expiring':
                $query->whereNotNull('expiry_date')
                      ->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays(90)->endOfDay()]);
                break;
        }

        $items = $query->orderBy('pharmacy_id')->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // Summary stats (across all approved pharmacies, unfiltered)
        $baseAll   = InventoryItem::whereHas('pharmacy', fn($pq) => $pq->where('status', 'approved'));
        $totalSkus      = (clone $baseAll)->count();
        $inStockCount   = (clone $baseAll)->where('stockQuantity', '>', 0)->count();
        $outOfStockCount= (clone $baseAll)->where('stockQuantity', '<=', 0)->count();
        $lowStockCount  = (clone $baseAll)->whereColumn('stockQuantity', '<=', 'par_level')->where('par_level', '>', 0)->count();

        // Per-pharmacy mini summaries (for overview cards when no filters are applied)
        $pharmacySummaries = [];
        $pharmacies = Pharmacy::where('status', 'approved')->orderBy('pharmacy_name')->get();

        foreach ($pharmacies as $pharm) {
            $base    = InventoryItem::where('pharmacy_id', $pharm->id);
            $total   = (clone $base)->count();
            $in      = (clone $base)->where('stockQuantity', '>', 0)->count();
            $out     = (clone $base)->where('stockQuantity', '<=', 0)->count();
            $low     = (clone $base)->whereColumn('stockQuantity', '<=', 'par_level')->where('par_level', '>', 0)->count();

            $pharmacySummaries[] = [
                'id'    => $pharm->id,
                'name'  => $pharm->pharmacy_name,
                'total' => $total,
                'in'    => $in,
                'out'   => $out,
                'low'   => $low,
            ];
        }

        $categories = Medicine::whereIn(
            'id',
            InventoryItem::whereHas('pharmacy', fn($pq) => $pq->where('status', 'approved'))->pluck('medicine_id')
        )->whereNotNull('category')->distinct()->pluck('category')->values();

        return view('admin.inventory', compact(
            'items',
            'pharmacies',
            'pharmacySummaries',
            'categories',
            'q', 'pharmacyId', 'stock', 'category',
            'totalSkus', 'inStockCount', 'outOfStockCount', 'lowStockCount'
        ));
    }
}
