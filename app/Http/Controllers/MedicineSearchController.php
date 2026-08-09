<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\SearchLog;
use Illuminate\Http\Request;

class MedicineSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('query');
        
        $results = InventoryItem::with(['pharmacy', 'medicine'])
            ->whereHas('medicine', function($q) use ($query) {
                $q->where('medicine_name', 'like', "%{$query}%");
            })
            ->whereHas('pharmacy', function($q) {
                $q->where('status', 'approved');
            })
            ->get();

        // Record search logs for each matched pharmacy (track store interest)
        if (!empty($query)) {
            $loggedPharmacyIds = [];
            foreach ($results as $item) {
                $pharmacyId = $item->pharmacy_id;
                if ($pharmacyId && !in_array($pharmacyId, $loggedPharmacyIds)) {
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
