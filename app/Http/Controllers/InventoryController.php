<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $q = $request->query('q', '');
        $category = $request->query('category', '');
        $sort = $request->query('sort', 'recent');
        $stock = $request->query('stock', '');
        $coldChain = $request->query('cold_chain', '');

        $query = InventoryItem::with('medicine', 'supplier')
            ->where('pharmacy_id', $pharmacy->id);

        if (!empty($q)) {
            $query->whereHas('medicine', function($mq) use ($q) {
                $mq->where('medicine_name', 'ilike', "%{$q}%")
                   ->orWhere('manufacturer', 'ilike', "%{$q}%");
            });
        }

        if (!empty($category)) {
            $query->whereHas('medicine', function($mq) use ($category) {
                $mq->where('category', $category);
            });
        }

        if (!empty($coldChain)) {
            $query->where('cold_chain', true);
        }

        switch ($stock) {
            case 'low':
                $query->belowPar();
                break;
            case 'out':
                $query->outOfStock();
                break;
            case 'in':
                $query->where('stockQuantity', '>', 0);
                break;
            case 'expiring':
                $query->expiringWithin(90);
                break;
            case 'expired':
                $query->expired();
                break;
        }

        switch ($sort) {
            case 'fefo':
                $query->fefo();
                break;
            case 'name':
                $query->whereHas('medicine', function($mq){ $mq->orderBy('medicine_name'); });
                break;
            case 'low':
                $query->orderBy('stockQuantity', 'asc');
                break;
            case 'high':
                $query->orderBy('stockQuantity', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $inventory = $query->paginate(15)->withQueryString();

        $inventoryMedicineNames = $inventory->pluck('medicine')->filter()->map(function($m){ return $m->medicine_name; })->unique()->values()->toArray();

        // Available categories for the filter dropdown (from medicines in this pharmacy).
        $categories = Medicine::whereIn('id', InventoryItem::where('pharmacy_id', $pharmacy->id)->pluck('medicine_id'))
            ->whereNotNull('category')
            ->pluck('category')
            ->unique()
            ->values()
            ->toArray();

        return view('pharmacy.inventory', compact(
            'pharmacy',
            'inventory',
            'inventoryMedicineNames',
            'q',
            'category',
            'sort',
            'stock',
            'coldChain',
            'categories'
        ));
    }

    public function create()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $medicines = Medicine::orderBy('medicine_name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        return view('pharmacy.inventory_create', compact('pharmacy', 'medicines', 'suppliers'));
    }

    public function store(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $data = $request->validate([
            'medicine_id' => 'nullable|exists:medicines,id',
            'medicine_name' => 'nullable|string|max:255',
            'dosage' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'stockQuantity' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'batch_number' => 'nullable|string|max:255',
            'cold_chain' => 'nullable',
            'par_level' => 'nullable|integer|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'category' => 'nullable|string|max:255',
        ]);

        if (empty($data['medicine_id']) && empty($data['medicine_name'])) {
            return redirect()->back()->with('error', 'Please select or enter a medicine name.')->withInput();
        }

        if (empty($data['medicine_id'])) {
            $medicine = Medicine::create([
                'medicine_name' => $data['medicine_name'],
                'dosage' => $data['dosage'] ?? '',
                'manufacturer' => $data['manufacturer'] ?? '',
                'category' => $data['category'] ?? null,
            ]);
        } else {
            $medicine = Medicine::find($data['medicine_id']);
            if (!empty($data['category'])) {
                $medicine->category = $data['category'];
                $medicine->save();
            }
        }

        $existing = InventoryItem::where('pharmacy_id', $pharmacy->id)->where('medicine_id', $medicine->id)->first();
        $before = $existing->stockQuantity ?? 0;

        $item = InventoryItem::updateOrCreate(
            ['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id],
            [
                'stockQuantity' => $data['stockQuantity'],
                'price' => $data['price'],
                'status' => 'available',
                'expiry_date' => $data['expiry_date'] ?? ($existing->expiry_date ?? null),
                'batch_number' => $data['batch_number'] ?? ($existing->batch_number ?? null),
                'cold_chain' => !empty($data['cold_chain']),
                'par_level' => $data['par_level'] ?? ($existing->par_level ?? 0),
                'supplier_id' => $data['supplier_id'] ?? ($existing->supplier_id ?? null),
            ]
        );

        $item->recordAudit($before, $item->stockQuantity, 'Added/updated inventory item');

        return redirect()->route('pharmacy.inventory')->with('success', 'Inventory item added/updated successfully.');
    }

    public function edit($id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::with('medicine', 'supplier')->where('id', $id)->where('pharmacy_id', $pharmacy->id)->firstOrFail();
        $suppliers = Supplier::orderBy('name')->get();
        return view('pharmacy.inventory_edit', compact('pharmacy', 'item', 'suppliers'));
    }

    public function update(Request $request, $id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::where('id', $id)->where('pharmacy_id', $pharmacy->id)->firstOrFail();

        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'stockQuantity' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'batch_number' => 'nullable|string|max:255',
            'cold_chain' => 'nullable',
            'par_level' => 'nullable|integer|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $before = $item->stockQuantity;

        $item->price = $data['price'];
        $item->stockQuantity = $data['stockQuantity'];
        $item->expiry_date = $data['expiry_date'] ?? null;
        $item->batch_number = $data['batch_number'] ?? null;
        $item->cold_chain = !empty($data['cold_chain']);
        $item->par_level = $data['par_level'] ?? 0;
        $item->supplier_id = $data['supplier_id'] ?? null;
        $item->save();

        $item->recordAudit($before, $item->stockQuantity, 'Manual stock update');

        return redirect()->route('pharmacy.inventory')->with('success', 'Inventory item updated.');
    }

    public function destroy($id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::where('id', $id)->where('pharmacy_id', $pharmacy->id)->first();
        if ($item) $item->delete();

        return redirect()->route('pharmacy.inventory')->with('success', 'Inventory item deleted.');
    }

    public function export(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $items = InventoryItem::with('medicine', 'supplier')
            ->where('pharmacy_id', $pharmacy->id)
            ->get();

        $filename = 'inventory_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($items) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Medicine', 'Dosage', 'Manufacturer', 'Category',
                'Stock', 'Price', 'Batch', 'Expiry', 'Cold Chain',
                'Par Level', 'Supplier', 'Segregation', 'ABC', 'VED', 'ABC-VED'
            ]);

            foreach ($items as $item) {
                fputcsv($out, [
                    optional($item->medicine)->medicine_name,
                    optional($item->medicine)->dosage,
                    optional($item->medicine)->manufacturer,
                    optional($item->medicine)->category,
                    $item->stockQuantity,
                    $item->price,
                    $item->batch_number,
                    optional($item->expiry_date)->format('Y-m-d'),
                    $item->cold_chain ? 'Yes' : 'No',
                    $item->par_level,
                    optional($item->supplier)->name,
                    $item->segregation,
                    $item->abc_class,
                    $item->ved_class,
                    $item->abc_ved_class,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
