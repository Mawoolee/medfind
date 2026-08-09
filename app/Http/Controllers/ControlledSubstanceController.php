<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\InventoryItem;
use App\Models\ControlledSubstanceLog;
use Illuminate\Http\Request;

class ControlledSubstanceController extends Controller
{
    public function index(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        // All controlled-substance inventory items for this pharmacy.
        $controlledItems = InventoryItem::with('medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->get()
            ->filter(function ($item) {
                return $item->is_controlled;
            });

        // Logbook entries.
        $action = $request->query('action', '');
        $logsQuery = ControlledSubstanceLog::with(['inventoryItem.medicine', 'user'])
            ->whereHas('inventoryItem', function ($q) use ($pharmacy) {
                $q->where('pharmacy_id', $pharmacy->id);
            })
            ->orderBy('logged_at', 'desc');

        if (!empty($action)) {
            $logsQuery->where('action', $action);
        }

        $logs = $logsQuery->get();

        $actions = ControlledSubstanceLog::whereHas('inventoryItem', function ($q) use ($pharmacy) {
            $q->where('pharmacy_id', $pharmacy->id);
        })->pluck('action')->unique()->values()->toArray();

        return view('pharmacy.controlled_substances_index', compact(
            'pharmacy',
            'controlledItems',
            'logs',
            'actions',
            'action'
        ));
    }
}
