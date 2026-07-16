<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\Http\Request;

class MedicineSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = trim($request->get('query', ''));

        $pharmacies = Pharmacy::with(['inventoryItems.medicine'])
            ->where('status', 'approved')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $results = $pharmacies->map(function ($pharmacy) use ($query) {
            $items = $pharmacy->inventoryItems;

            if ($query !== '') {
                $items = $items->filter(function ($item) use ($query) {
                    return stripos($item->medicine->name, $query) !== false
                        && $item->stock_quantity > 0;
                });
            } else {
                $items = $items->filter(fn($item) => $item->stock_quantity > 0);
            }

            if ($items->isEmpty()) {
                return null;
            }

            return [
                'id'      => $pharmacy->id,
                'name'    => $pharmacy->name,
                'address' => $pharmacy->address,
                'lat'     => (float) $pharmacy->latitude,
                'lng'     => (float) $pharmacy->longitude,
                'medicines' => $items->map(function ($item) {
                    return [
                        'name'         => $item->medicine->name . ' ' . $item->dosage,
                        'price'        => (float) $item->price,
                        'stock'        => $item->stock_quantity,
                        'prescription' => (bool) $item->medicine->requires_prescription,
                    ];
                })->values(),
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data'    => $results,
        ]);
    }
}
