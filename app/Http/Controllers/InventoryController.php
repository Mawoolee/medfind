<?php

namespace App\Http\Controllers;

use App\Events\InventoryUpdated;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $q = $request->query('q', '');
        $category = $request->query('category', '');
        $sort = $request->query('sort', 'recent');
        $stock = $request->query('stock', '');
        $coldChain = $request->query('cold_chain', '');

        $query = InventoryItem::with('medicine', 'supplier')
            ->where('pharmacy_id', $pharmacy->id);

        if (! empty($q)) {
            $query->whereHas('medicine', function ($mq) use ($q) {
                $mq->where('medicine_name', 'like', "%{$q}%")
                    ->orWhere('manufacturer', 'like', "%{$q}%");
            });
        }

        if (! empty($category)) {
            $query->whereHas('medicine', function ($mq) use ($category) {
                $mq->where('category', $category);
            });
        }

        if (! empty($coldChain)) {
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
                $query->whereHas('medicine', function ($mq) {
                    $mq->orderBy('medicine_name');
                });
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

        $inventoryMedicineNames = $inventory->pluck('medicine')->filter()->map(function ($m) {
            return $m->medicine_name;
        })->unique()->values()->toArray();

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
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $medicines = Medicine::with([
            'inventory' => fn ($query) => $query
                ->where('pharmacy_id', $pharmacy->id)
                ->with('supplier'),
        ])->orderBy('medicine_name')->get();

        $medicineAutofill = $medicines->mapWithKeys(function (Medicine $medicine) {
            $inventory = $medicine->inventory->first();

            return [
                (string) $medicine->id => [
                    'generic_name' => $medicine->medicine_name,
                    'brand_name' => $medicine->brand_name,
                    'dosage' => $medicine->dosage,
                    'batch_number' => $inventory?->batch_number,
                    'lot_number' => $inventory?->lot_number,
                    'price' => $inventory ? (string) $inventory->price : '',
                    'stock_quantity' => $inventory?->stockQuantity ?? 0,
                    'par_level' => $inventory?->par_level ?? 0,
                    'category' => $medicine->category,
                    'supplier_name' => $inventory?->supplier?->name,
                    'manufacturer' => $medicine->manufacturer,
                    'expiry_date' => $inventory?->expiry_date?->format('Y-m-d'),
                    'cold_chain' => (bool) ($inventory?->cold_chain ?? false),
                ],
            ];
        });

        return view('pharmacy.inventory_create', compact('pharmacy', 'medicines', 'medicineAutofill'));
    }

    public function store(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $data = $request->validate([
            'medicine_id' => 'nullable|exists:medicines,id',
            'medicine_name' => 'nullable|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'dosage' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:255',
            'lot_number' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'stockQuantity' => 'required|integer|min:0',
            'par_level' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:255',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'manufacturer' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date',
            'cold_chain' => 'nullable|boolean',
        ]);

        if (empty($data['medicine_id']) && blank($data['medicine_name'] ?? null)) {
            return redirect()->back()
                ->withErrors(['medicine_name' => 'The generic name field is required when no existing medicine is selected.'])
                ->with('error', 'Please select or enter a medicine name.')
                ->withInput();
        }

        if (array_key_exists('medicine_name', $data) && blank($data['medicine_name'])) {
            return redirect()->back()
                ->withErrors(['medicine_name' => 'The generic name field is required.'])
                ->withInput();
        }

        [$medicine, $item] = DB::transaction(function () use ($data, $pharmacy) {
            if (empty($data['medicine_id'])) {
                $medicine = Medicine::create([
                    'medicine_name' => trim($data['medicine_name']),
                    'brand_name' => $data['brand_name'] ?? null,
                    'dosage' => $data['dosage'] ?? '',
                    'manufacturer' => $data['manufacturer'] ?? '',
                    'category' => $data['category'] ?? null,
                ]);
            } else {
                $medicine = Medicine::findOrFail($data['medicine_id']);
                $medicineUpdates = [];

                foreach (['brand_name', 'dosage', 'manufacturer', 'category'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $medicineUpdates[$field] = in_array($field, ['dosage', 'manufacturer'], true)
                            ? (string) ($data[$field] ?? '')
                            : $data[$field];
                    }
                }

                if (array_key_exists('medicine_name', $data)) {
                    $medicineUpdates['medicine_name'] = trim($data['medicine_name']);
                }

                if ($medicineUpdates !== []) {
                    $medicine->update($medicineUpdates);
                }
            }

            $existing = InventoryItem::where('pharmacy_id', $pharmacy->id)
                ->where('medicine_id', $medicine->id)
                ->first();
            $before = (int) ($existing?->stockQuantity ?? 0);

            $optionalValue = static fn (string $key, mixed $fallback = null): mixed => array_key_exists($key, $data)
                ? $data[$key]
                : $fallback;

            $supplierId = $existing?->supplier_id;
            if (array_key_exists('supplier_name', $data)) {
                $supplierName = trim((string) ($data['supplier_name'] ?? ''));
                $supplierId = null;

                if ($supplierName !== '') {
                    $normalizedSupplierName = mb_strtolower($supplierName, 'UTF-8');
                    $supplier = Supplier::whereRaw('LOWER(TRIM(name)) = ?', [$normalizedSupplierName])->first();
                    $supplier ??= Supplier::create(['name' => $supplierName]);
                    $supplierId = $supplier->id;
                }
            } elseif (array_key_exists('supplier_id', $data)) {
                $supplierId = $data['supplier_id'];
            }

            $item = InventoryItem::updateOrCreate(
                ['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id],
                [
                    'stockQuantity' => $data['stockQuantity'],
                    'price' => $data['price'],
                    'status' => 'available',
                    'expiry_date' => $optionalValue('expiry_date', $existing?->expiry_date),
                    'batch_number' => $optionalValue('batch_number', $existing?->batch_number),
                    'lot_number' => $optionalValue('lot_number', $existing?->lot_number),
                    'cold_chain' => ! empty($data['cold_chain']),
                    'par_level' => $optionalValue('par_level', $existing?->par_level ?? 0) ?? 0,
                    'supplier_id' => $supplierId,
                ]
            );

            $item->recordAudit($before, (int) $item->stockQuantity, 'Added/updated inventory item');

            return [$medicine, $item];
        });

        // Broadcast real-time inventory update to public map & pharmacy channel
        InventoryUpdated::dispatch(
            $pharmacy->id,
            $item->medicine_id,
            $medicine->medicine_name,
            (int) $item->stockQuantity,
            (float) $item->price,
            (bool) $medicine->requiresPrescription
        );

        return redirect()->route('pharmacy.inventory')->with('success', 'Inventory item added/updated successfully.');
    }

    public function edit($id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::with('medicine', 'supplier')->where('id', $id)->where('pharmacy_id', $pharmacy->id)->firstOrFail();
        $suppliers = Supplier::orderBy('name')->get();

        return view('pharmacy.inventory_edit', compact('pharmacy', 'item', 'suppliers'));
    }

    public function update(Request $request, $id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::where('id', $id)->where('pharmacy_id', $pharmacy->id)->firstOrFail();

        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'stockQuantity' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'batch_number' => 'nullable|string|max:255',
            'lot_number' => 'nullable|string|max:255',
            'cold_chain' => 'nullable|boolean',
            'par_level' => 'nullable|integer|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $before = $item->stockQuantity;

        $item->price = $data['price'];
        $item->stockQuantity = $data['stockQuantity'];
        $item->expiry_date = $data['expiry_date'] ?? null;
        $item->batch_number = $data['batch_number'] ?? null;
        $item->lot_number = $data['lot_number'] ?? null;
        $item->cold_chain = ! empty($data['cold_chain']);
        $item->par_level = $data['par_level'] ?? 0;
        $item->supplier_id = $data['supplier_id'] ?? null;
        $item->save();

        $item->recordAudit($before, $item->stockQuantity, 'Manual stock update');

        // Broadcast real-time inventory update to public map & pharmacy channel
        InventoryUpdated::dispatch(
            $pharmacy->id,
            $item->medicine_id,
            $item->medicine->medicine_name ?? null,
            (int) $item->stockQuantity,
            (float) $item->price,
            (bool) optional($item->medicine)->requiresPrescription
        );

        return redirect()->route('pharmacy.inventory')->with('success', 'Inventory item updated.');
    }

    public function destroy($id)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::where('id', $id)->where('pharmacy_id', $pharmacy->id)->first();
        if ($item) {
            $medicineId = $item->medicine_id;
            $medicineName = $item->medicine->medicine_name ?? null;
            $price = (float) $item->price;
            $prescription = (bool) optional($item->medicine)->requiresPrescription;
            $item->delete();

            // Broadcast real-time inventory update (stock 0) to public map & pharmacy channel
            InventoryUpdated::dispatch(
                $pharmacy->id,
                $medicineId,
                $medicineName,
                0,
                $price,
                $prescription
            );
        }

        return redirect()->route('pharmacy.inventory')->with('success', 'Inventory item deleted.');
    }

    public function export(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $items = InventoryItem::with('medicine', 'supplier')
            ->where('pharmacy_id', $pharmacy->id)
            ->get();

        $filename = 'inventory_'.date('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($items) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Generic Name', 'Brand Name', 'Dosage', 'Manufacturer', 'Category',
                'Stock', 'Price', 'Batch', 'Lot', 'Expiry', 'Cold Chain',
                'Par Level', 'Supplier', 'Segregation', 'ABC', 'VED', 'ABC-VED',
            ]);

            foreach ($items as $item) {
                fputcsv($out, [
                    optional($item->medicine)->medicine_name,
                    optional($item->medicine)->brand_name,
                    optional($item->medicine)->dosage,
                    optional($item->medicine)->manufacturer,
                    optional($item->medicine)->category,
                    $item->stockQuantity,
                    $item->price,
                    $item->batch_number,
                    $item->lot_number,
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
