<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function index(Request $request, InventoryAggregateQuery $aggregateQuery)
    {
        $q = $request->query('q', '');
        $pharmacyId = $request->query('pharmacy_id', '');
        $stock = $request->query('stock', '');
        $category = $request->query('category', '');

        $query = InventoryItem::with(['pharmacy', 'medicine'])
            ->whereHas('pharmacy', fn ($pharmacyQuery) => $pharmacyQuery->where('status', 'approved'));
        $aggregateQuery->withProjections($query);

        if (! empty($q)) {
            $query->whereHas('medicine', fn ($medicineQuery) => $medicineQuery
                ->where('medicine_name', 'like', "%{$q}%")
                ->orWhere('dosage', 'like', "%{$q}%"));
        }

        if (! empty($pharmacyId)) {
            $query->where('pharmacy_id', $pharmacyId);
        }

        if (! empty($category)) {
            $query->whereHas('medicine', fn ($medicineQuery) => $medicineQuery->where('category', $category));
        }

        match ($stock) {
            'in' => $aggregateQuery->available($query),
            'out' => $aggregateQuery->outOfStock($query),
            'low' => $aggregateQuery->belowPar($query),
            'expiring' => $aggregateQuery->expiringWithin($query, 90),
            default => $query,
        };

        $items = $query
            ->orderBy('pharmacy_id')
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Summary stats (across all approved pharmacies, unfiltered)
        $baseAll = InventoryItem::whereHas(
            'pharmacy',
            fn ($pharmacyQuery) => $pharmacyQuery->where('status', 'approved')
        );
        $systemCounts = $this->stockCounts($baseAll, $aggregateQuery);
        $totalSkus = $systemCounts['total'];
        $inStockCount = $systemCounts['in'];
        $outOfStockCount = $systemCounts['out'];
        $lowStockCount = $systemCounts['low'];

        // Per-pharmacy mini summaries (for overview cards when no filters are applied)
        $pharmacySummaries = [];
        $pharmacies = Pharmacy::where('status', 'approved')->orderBy('pharmacy_name')->get();

        foreach ($pharmacies as $pharmacy) {
            $counts = $this->stockCounts(
                InventoryItem::where('pharmacy_id', $pharmacy->id),
                $aggregateQuery
            );

            $pharmacySummaries[] = [
                'id' => $pharmacy->id,
                'name' => $pharmacy->pharmacy_name,
                ...$counts,
            ];
        }

        $categories = Medicine::whereIn(
            'id',
            InventoryItem::whereHas(
                'pharmacy',
                fn ($pharmacyQuery) => $pharmacyQuery->where('status', 'approved')
            )->pluck('medicine_id')
        )->whereNotNull('category')->distinct()->pluck('category')->values();

        return view('admin.inventory', compact(
            'items',
            'pharmacies',
            'pharmacySummaries',
            'categories',
            'q',
            'pharmacyId',
            'stock',
            'category',
            'totalSkus',
            'inStockCount',
            'outOfStockCount',
            'lowStockCount'
        ));
    }

    /**
     * @return array{total: int, in: int, out: int, low: int}
     */
    private function stockCounts(Builder $base, InventoryAggregateQuery $aggregateQuery): array
    {
        $inStock = clone $base;
        $aggregateQuery->available($inStock);

        $outOfStock = clone $base;
        $aggregateQuery->outOfStock($outOfStock);

        $lowStock = clone $base;
        $aggregateQuery->belowPar($lowStock);

        return [
            'total' => (clone $base)->count(),
            'in' => $inStock->count(),
            'out' => $outOfStock->count(),
            'low' => $lowStock->count(),
        ];
    }
}
