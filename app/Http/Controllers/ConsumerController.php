<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\SearchLog;
use Illuminate\Http\Request;

class ConsumerController extends Controller
{
    public function index(InventoryAggregateQuery $aggregateQuery)
    {
        // Get all approved pharmacies with their inventory and medicine relationships
        $pharmacies = Pharmacy::where('status', 'approved')
            ->with([
                'inventory' => fn ($relation) => $aggregateQuery->withProjections($relation->getQuery()),
                'inventory.medicine',
            ])
            ->get();

        // Pre-compute most searched medicine per pharmacy from SearchLog
        $topSearchedByPharmacy = SearchLog::selectRaw('pharmacy_id, query, COUNT(*) as total')
            ->whereNotNull('query')
            ->groupBy('pharmacy_id', 'query')
            ->orderBy('total', 'desc')
            ->get()
            ->groupBy('pharmacy_id')
            ->map(fn ($rows) => $rows->take(3)->pluck('query')->values()->toArray());

        // Format pharmacies for the frontend
        $formattedPharmacies = $pharmacies->map(function ($pharmacy) use ($topSearchedByPharmacy) {
            return [
                'id' => $pharmacy->id,
                'name' => $pharmacy->pharmacy_name,
                'address' => $pharmacy->pharmacyAddress,
                'lat' => (float) $pharmacy->latitude,
                'lng' => (float) $pharmacy->longitude,
                'contactNumber' => $pharmacy->contactNumber,
                'hours' => $pharmacy->operating_hours,
                'logo' => $pharmacy->logo_path ? asset('storage/'.$pharmacy->logo_path) : null,
                'mostSearched' => $topSearchedByPharmacy[$pharmacy->id] ?? [],
                'medicines' => $pharmacy->inventory->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->medicine->medicine_name ?? 'Unknown',
                        'dosage' => $item->medicine->dosage ?? null,
                        'manufacturer' => $item->medicine->manufacturer ?? null,
                        'category' => $item->medicine->category ?? null,
                        'price' => (float) $item->representative_price,
                        'stock' => (int) $item->available_stock,
                        'prescription' => $item->medicine->requiresPrescription ?? false,
                    ];
                })->toArray(),
            ];
        })->toArray();

        // Get all unique medicine names for autocomplete
        $medicineNames = Medicine::pluck('medicine_name')->unique()->toArray();

        // Get total pharmacy count
        $pharmacyCount = Pharmacy::where('status', 'approved')->count();

        // Get total medicines with batch-derived available stock
        $medicineStockQuery = InventoryItem::query();
        $aggregateQuery->available($medicineStockQuery);
        $medicineStockCount = $medicineStockQuery
            ->distinct()
            ->count('medicine_id');

        return view('consumer.dashboard', compact(
            'formattedPharmacies',
            'medicineNames',
            'pharmacyCount',
            'medicineStockCount'
        ));
    }

    public function pharmacyDetails($id, InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::with([
            'inventory' => fn ($relation) => $aggregateQuery->withProjections($relation->getQuery()),
            'inventory.medicine',
        ])
            ->where('status', 'approved')
            ->findOrFail($id);

        return view('consumer.pharmacy-details', compact('pharmacy'));
    }

    public function search(Request $request, InventoryAggregateQuery $aggregateQuery)
    {
        $query = $request->get('query');

        $resultsQuery = InventoryItem::with(['pharmacy', 'medicine'])
            ->whereHas('medicine', function ($medicineQuery) use ($query) {
                $medicineQuery->where('medicine_name', 'like', "%{$query}%");
            })
            ->whereHas('pharmacy', function ($pharmacyQuery) {
                $pharmacyQuery->where('status', 'approved');
            });
        $aggregateQuery->withProjections($resultsQuery);
        $results = $resultsQuery
            ->paginate(20)
            ->withQueryString();

        // Record search logs for each matched pharmacy (track store interest)
        if (! empty($query)) {
            $loggedPharmacyIds = [];
            foreach ($results as $item) {
                $pharmacyId = $item->pharmacy_id;
                if ($pharmacyId && ! in_array($pharmacyId, $loggedPharmacyIds, true)) {
                    SearchLog::create([
                        'pharmacy_id' => $pharmacyId,
                        'query' => $query,
                    ]);
                    $loggedPharmacyIds[] = $pharmacyId;
                }
            }
        }

        return view('consumer.search', compact('results', 'query'));
    }
}
