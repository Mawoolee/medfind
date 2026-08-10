<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\SearchLog;
use Illuminate\Http\Request;

class ConsumerController extends Controller
{
    public function index()
    {
        // Get all approved pharmacies with their inventory and medicine relationships
        $pharmacies = Pharmacy::where('status', 'approved')
            ->with('inventory.medicine')
            ->get();

        // Format pharmacies for the frontend
        $formattedPharmacies = $pharmacies->map(function($pharmacy) {
            return [
                'id' => $pharmacy->id,
                'name' => $pharmacy->pharmacy_name,
                'address' => $pharmacy->pharmacyAddress,
                'lat' => (float) $pharmacy->latitude,
                'lng' => (float) $pharmacy->longitude,
                'medicines' => $pharmacy->inventory->map(function($item) {
                    return [
                        'name' => $item->medicine->medicine_name ?? 'Unknown',
                        'price' => (float) $item->price,
                        'stock' => (int) $item->stockQuantity,
                        'prescription' => $item->medicine->requiresPrescription ?? false
                    ];
                })->toArray()
            ];
        })->toArray();

        // Get all unique medicine names for autocomplete
        $medicineNames = Medicine::pluck('medicine_name')->unique()->toArray();

        // Get total pharmacy count
        $pharmacyCount = Pharmacy::where('status', 'approved')->count();

        // Get total medicines in stock
        $medicineStockCount = InventoryItem::where('stockQuantity', '>', 0)
            ->distinct('medicine_id')
            ->count('medicine_id');

        return view('consumer.dashboard', compact(
            'formattedPharmacies', 
            'medicineNames', 
            'pharmacyCount', 
            'medicineStockCount'
        ));
    }

    public function pharmacyDetails($id)
    {
        $pharmacy = Pharmacy::with('inventory.medicine')
            ->where('status', 'approved')
            ->findOrFail($id);

        return view('consumer.pharmacy-details', compact('pharmacy'));
    }

public function search(Request $request)
    {
        $query = $request->get('query');

        $results = InventoryItem::with(['pharmacy', 'medicine'])
            ->whereHas('medicine', function ($q) use ($query) {
                $q->where('medicine_name', 'like', "%{$query}%");
            })
            ->whereHas('pharmacy', function ($q) {
                $q->where('status', 'approved');
            })
            ->paginate(20)
            ->withQueryString();

        // Record search logs for each matched pharmacy (track store interest)
        if (!empty($query)) {
            $loggedPharmacyIds = [];
            foreach ($results as $item) {
                $pharmacyId = $item->pharmacy_id;
                if ($pharmacyId && !in_array($pharmacyId, $loggedPharmacyIds)) {
                    SearchLog::create([
                        'pharmacy_id' => $pharmacyId,
                        'query'       => $query,
                    ]);
                    $loggedPharmacyIds[] = $pharmacyId;
                }
            }
        }

        return view('consumer.search', compact('results', 'query'));
    }
}