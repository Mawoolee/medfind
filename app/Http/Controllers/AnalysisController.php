<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryAggregateQuery;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    public function index(Request $request, InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $itemsQuery = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id);
        $aggregateQuery->withProjections($itemsQuery);
        $items = $itemsQuery->get();

        // Compute value per item (available stock * representative batch price).
        $items->each(function ($item): void {
            $item->value = (int) $item->available_stock * (float) $item->representative_price;
        });

        // Sort by value descending for ABC accumulation.
        $sorted = $items->sortByDesc('value')->values();
        $totalValue = $sorted->sum('value');

        // ABC classification based on cumulative value percentage.
        $cumulative = 0;
        $sorted->each(function ($item) use (&$cumulative, $totalValue): void {
            if ($totalValue <= 0) {
                $item->abc = 'C';

                return;
            }

            $cumulative += $item->value;
            $percentage = ($cumulative / $totalValue) * 100;
            if ($percentage <= 70) {
                $item->abc = 'A';
            } elseif ($percentage <= 90) {
                $item->abc = 'B';
            } else {
                $item->abc = 'C';
            }
        });

        // VED and ABC-VED use the authoritative ABC result calculated above.
        $abcVedMap = [
            'A' => ['V' => 'I', 'E' => 'I', 'D' => 'II'],
            'B' => ['V' => 'I', 'E' => 'II', 'D' => 'III'],
            'C' => ['V' => 'II', 'E' => 'III', 'D' => 'III'],
        ];
        $sorted->each(function ($item) use ($abcVedMap): void {
            $item->ved = $item->ved_class;
            $item->abc_ved = $abcVedMap[$item->abc][$item->ved] ?? 'III';
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
