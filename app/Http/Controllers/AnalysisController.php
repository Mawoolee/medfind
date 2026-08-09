<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    public function index(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $items = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->get();

        // Compute value per item (stock * price) as proxy for annual usage value.
        $items->each(function ($item) {
            $item->value = (float) $item->stockQuantity * (float) $item->price;
        });

        // Sort by value descending for ABC accumulation.
        $sorted = $items->sortByDesc('value')->values();
        $totalValue = $sorted->sum('value');

        // ABC classification based on cumulative value percentage.
        $cumulative = 0;
        $sorted->each(function ($item) use (&$cumulative, $totalValue) {
            if ($totalValue <= 0) {
                $item->abc = 'C';
                return;
            }
            $cumulative += $item->value;
            $pct = ($cumulative / $totalValue) * 100;
            if ($pct <= 70) {
                $item->abc = 'A';
            } elseif ($pct <= 90) {
                $item->abc = 'B';
            } else {
                $item->abc = 'C';
            }
        });

        // VED and ABC-VED via model helpers.
        $sorted->each(function ($item) {
            $item->ved = $item->ved_class;
            $item->abc_ved = $item->abc_ved_class;
        });

        // Matrix counts.
        $matrix = [
            'I' => $sorted->where('abc_ved', 'I')->count(),
            'II' => $sorted->where('abc_ved', 'II')->count(),
            'III' => $sorted->where('abc_ved', 'III')->count(),
        ];

        $abcCounts = [
            'A' => $sorted->where('abc', 'A')->count(),
            'B' => $sorted->where('abc', 'B')->count(),
            'C' => $sorted->where('abc', 'C')->count(),
        ];

        $vedCounts = [
            'V' => $sorted->where('ved', 'V')->count(),
            'E' => $sorted->where('ved', 'E')->count(),
            'D' => $sorted->where('ved', 'D')->count(),
        ];

        return view('pharmacy.analysis', compact(
            'pharmacy',
            'sorted',
            'totalValue',
            'matrix',
            'abcCounts',
            'vedCounts'
        ));
    }
}
