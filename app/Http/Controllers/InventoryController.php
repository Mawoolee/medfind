<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryAggregateQuery;
use App\Events\InventoryUpdated;
use App\Http\Requests\Inventory\StoreMedicineRequest;
use App\Http\Requests\Inventory\UpdateMedicineRequest;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Services\MedicineMasterService;
use App\Support\MedicineCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request, InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $q = (string) $request->query('q', '');
        $category = MedicineCategory::optionValue((string) $request->query('category', ''));
        $sort = (string) $request->query('sort', 'recent');
        $stock = (string) $request->query('stock', '');

        $query = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->id);
        $aggregateQuery->withProjections($query);

        if ($q !== '') {
            $query->whereHas('medicine', function (Builder $medicine) use ($q): void {
                $medicine->where(function (Builder $search) use ($q): void {
                    $search->where('medicine_name', 'like', "%{$q}%")
                        ->orWhere('brand_name', 'like', "%{$q}%")
                        ->orWhere('manufacturer', 'like', "%{$q}%");
                });
            });
        }

        if ($category !== '') {
            $query->whereHas('medicine', fn (Builder $medicine) => $medicine
                ->whereRaw('LOWER(TRIM(category)) = LOWER(TRIM(?))', [$category]));
        }

        match ($stock) {
            'low' => $aggregateQuery->belowPar($query),
            'out' => $aggregateQuery->outOfStock($query),
            'in' => $aggregateQuery->available($query),
            'expiring' => $aggregateQuery->expiringWithin($query, 90),
            'expired' => $aggregateQuery->expiredPhysicalStock($query),
            default => $query,
        };

        match ($sort) {
            'fefo' => $aggregateQuery->orderByNearestValidExpiry($query)->orderBy('id'),
            'name' => $query->orderBy(
                Medicine::query()
                    ->select('medicine_name')
                    ->whereColumn('medicines.id', 'inventory_items.medicine_id')
                    ->limit(1)
            ),
            'low' => $query->orderBy('available_stock')->orderBy('id'),
            'high' => $query->orderByDesc('available_stock')->orderBy('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };

        $inventory = $query->paginate(15)->withQueryString();
        $inventoryMedicineNames = $inventory->getCollection()
            ->pluck('medicine.medicine_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $storedCategories = Medicine::query()
            ->whereIn('id', InventoryItem::query()->where('pharmacy_id', $pharmacy->id)->select('medicine_id'))
            ->whereNotNull('category')
            ->orderBy('category')
            ->pluck('category');
        $categoryOptions = MedicineCategory::optionsWithCustom($storedCategories);

        return view('pharmacy.inventory', compact(
            'pharmacy',
            'inventory',
            'inventoryMedicineNames',
            'q',
            'category',
            'sort',
            'stock',
            'categoryOptions'
        ));
    }

    public function create()
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $medicines = Medicine::query()
            ->with(['inventory' => fn ($query) => $query->where('pharmacy_id', $pharmacy->id)])
            ->orderBy('medicine_name')
            ->get();

        $medicineAutofill = $medicines->mapWithKeys(function (Medicine $medicine): array {
            $aggregate = $medicine->inventory->first();

            return [
                (string) $medicine->id => [
                    'medicine_name' => $medicine->medicine_name,
                    'brand_name' => $medicine->brand_name,
                    'dosage' => $medicine->dosage,
                    'category' => MedicineCategory::optionValue($medicine->category),
                    'manufacturer' => $medicine->manufacturer,
                    'requires_prescription' => (bool) $medicine->requiresPrescription,
                    'cold_chain_required' => (bool) $medicine->cold_chain_required,
                    'par_level' => $aggregate?->par_level ?? 0,
                ],
            ];
        });

        $categoryOptions = MedicineCategory::optionsWithCustom([old('category')]);
        $selectedCategory = MedicineCategory::optionValue(old('category'));

        return view('pharmacy.inventory_create', compact(
            'pharmacy',
            'medicines',
            'medicineAutofill',
            'categoryOptions',
            'selectedCategory'
        ));
    }

    public function store(StoreMedicineRequest $request, MedicineMasterService $medicineMaster)
    {
        $aggregate = $medicineMaster->createForPharmacy(
            $request->pharmacy(),
            $request->medicineAttributes(),
            $request->parLevel(),
        );

        return redirect()
            ->route('pharmacy.inventory')
            ->with('success', "{$aggregate->medicine->medicine_name} was added to the medicine catalog. Use Add Stock to receive a batch.");
    }

    public function edit(int|string $id, InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $query = InventoryItem::query()
            ->with('medicine')
            ->whereKey($id)
            ->where('pharmacy_id', $pharmacy->id);
        $aggregateQuery->withProjections($query);
        $item = $query->firstOrFail();
        $selectedCategory = MedicineCategory::optionValue(old('category', $item->medicine->category));
        $categoryOptions = MedicineCategory::optionsWithCustom([
            $item->medicine->category,
            old('category'),
        ]);

        return view('pharmacy.inventory_edit', compact(
            'pharmacy',
            'item',
            'categoryOptions',
            'selectedCategory'
        ));
    }

    public function update(
        UpdateMedicineRequest $request,
        int|string $id,
        MedicineMasterService $medicineMaster,
    ) {
        $aggregate = $medicineMaster->updateForPharmacy(
            $request->aggregate(),
            $request->medicineAttributes(),
            $request->parLevel(),
        );

        return redirect()
            ->route('pharmacy.inventory')
            ->with('success', "{$aggregate->medicine->medicine_name} medicine details were updated. Batch stock was not changed.");
    }

    public function destroy(int|string $id)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $item = InventoryItem::query()
            ->with('medicine')
            ->whereKey($id)
            ->where('pharmacy_id', $pharmacy->id)
            ->firstOrFail();

        if ($item->batches()->exists() || $item->audits()->exists() || $item->stockMovements()->exists()) {
            return redirect()->back()->with('error', 'Medicine inventory with batch or stock history cannot be deleted.');
        }

        $medicineId = $item->medicine_id;
        $medicineName = $item->medicine?->medicine_name;
        $prescription = (bool) $item->medicine?->requiresPrescription;
        $item->delete();

        InventoryUpdated::dispatch(
            $pharmacy->id,
            $medicineId,
            $medicineName,
            0,
            0,
            $prescription,
        );

        return redirect()->route('pharmacy.inventory')->with('success', 'Medicine was removed from this pharmacy catalog.');
    }

    public function export(Request $request, InventoryAggregateQuery $aggregateQuery)
    {
        $pharmacy = Pharmacy::query()->where('user_id', auth()->id())->first();
        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $query = InventoryItem::query()
            ->with('medicine')
            ->withCount('batches')
            ->where('pharmacy_id', $pharmacy->id)
            ->orderBy('id');
        $aggregateQuery->withProjections($query);
        $items = $query->get();

        $filename = 'inventory_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = static function () use ($items): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Generic Name', 'Brand Name', 'Dosage', 'Manufacturer', 'Category',
                'Available Stock', 'Physical Stock', 'Representative Price', 'Batch Count',
                'Nearest Valid Expiry', 'Cold Chain Required', 'Par Level',
                'Segregation', 'ABC', 'VED', 'ABC-VED',
            ]);

            foreach ($items as $item) {
                fputcsv($out, [
                    $item->medicine?->medicine_name,
                    $item->medicine?->brand_name,
                    $item->medicine?->dosage,
                    $item->medicine?->manufacturer,
                    $item->medicine?->category,
                    $item->available_stock,
                    $item->physical_stock,
                    $item->representative_price,
                    $item->batches_count,
                    $item->nearest_valid_expiry?->format('Y-m-d'),
                    $item->medicine?->cold_chain_required ? 'Yes' : 'No',
                    $item->par_level,
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
