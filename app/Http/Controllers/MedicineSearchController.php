<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Illuminate\Http\Request;

class MedicineSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('query');
        
        $results = InventoryItem::with(['pharmacy', 'medicine'])
            ->whereHas('medicine', function($q) use ($query) {
                $q->where('medicine_name', 'ILIKE', "%{$query}%");
            })
            ->whereHas('pharmacy', function($q) {
                $q->where('status', 'approved');
            })
            ->get();
            
        return view('consumer.search', compact('results', 'query'));
    }
}