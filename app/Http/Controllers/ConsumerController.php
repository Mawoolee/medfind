<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\View\View;

class ConsumerController extends Controller
{
    public function index(): View
    {
        $pharmacies = Pharmacy::with(['inventoryItems.medicine'])
            ->where('status', 'approved')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($pharmacy) {
                return [
                    'id'       => $pharmacy->id,
                    'name'     => $pharmacy->name,
                    'address'  => $pharmacy->address,
                    'lat'      => (float) $pharmacy->latitude,
                    'lng'      => (float) $pharmacy->longitude,
                    'contact'  => $pharmacy->contact_number,
                    'medicines' => $pharmacy->inventoryItems->map(function ($item) {
                        return [
                            'name'         => $item->medicine->name . ' ' . $item->dosage,
                            'price'        => (float) $item->price,
                            'stock'        => $item->stock_quantity,
                            'prescription' => (bool) $item->medicine->requires_prescription,
                        ];
                    })->values(),
                ];
            });

        $pharmaciesJson = $pharmacies->toJson();

        return view('welcome', compact('pharmaciesJson'));
    }
}
