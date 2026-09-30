<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Support\MedicineCategory;
use App\Support\MedicineIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MedicineMasterService
{
    private const MEDICINE_FIELDS = [
        'medicine_name',
        'brand_name',
        'dosage',
        'category',
        'manufacturer',
        'requiresPrescription',
        'cold_chain_required',
    ];

    private const STOCK_FIELDS = [
        'stockQuantity',
        'stock_quantity',
        'quantity',
        'quantity_received',
        'current_quantity',
        'price',
        'expiry_date',
        'batch_number',
        'lot_number',
        'cold_chain',
        'supplier_id',
        'supplier_name',
        'received_date',
        'received_reference',
        'purchase_order',
    ];

    public function createForPharmacy(Pharmacy $pharmacy, array $attributes, int $parLevel): InventoryItem
    {
        $this->rejectStockFields($attributes);
        $validated = $this->validateMedicineAttributes($attributes);
        $this->validateParLevel($parLevel);

        return DB::transaction(function () use ($pharmacy, $validated, $parLevel): InventoryItem {
            $medicine = $this->resolveMedicine($validated);

            $aggregate = InventoryItem::query()->firstOrCreate(
                [
                    'pharmacy_id' => $pharmacy->getKey(),
                    'medicine_id' => $medicine->getKey(),
                ],
                [
                    'stockQuantity' => 0,
                    'price' => 0,
                    'status' => 'out_of_stock',
                    'par_level' => $parLevel,
                ],
            );

            if ((int) $aggregate->par_level !== $parLevel) {
                $aggregate->par_level = $parLevel;
                $aggregate->save();
            }

            return $aggregate->refresh()->load('medicine');
        });
    }

    public function updateForPharmacy(InventoryItem $aggregate, array $attributes, int $parLevel): InventoryItem
    {
        $this->rejectStockFields($attributes);
        $validated = $this->validateMedicineAttributes($attributes);
        $this->validateParLevel($parLevel);

        return DB::transaction(function () use ($aggregate, $validated, $parLevel): InventoryItem {
            $lockedAggregate = InventoryItem::query()
                ->lockForUpdate()
                ->findOrFail($aggregate->getKey());

            if (isset($validated['medicine_id']) && (int) $validated['medicine_id'] !== (int) $lockedAggregate->medicine_id) {
                throw ValidationException::withMessages([
                    'medicine_id' => 'The medicine assigned to an inventory aggregate cannot be changed.',
                ]);
            }

            $medicine = Medicine::query()
                ->lockForUpdate()
                ->findOrFail($lockedAggregate->medicine_id);

            $this->ensureMedicineIdentityIsAvailable($validated, $medicine);
            $medicine->fill($this->medicineValues($validated, $medicine));
            if ($medicine->isDirty()) {
                $medicine->save();
            }

            if ((int) $lockedAggregate->par_level !== $parLevel) {
                $lockedAggregate->par_level = $parLevel;
                $lockedAggregate->save();
            }

            return $lockedAggregate->refresh()->load('medicine');
        });
    }

    private function resolveMedicine(array $validated): Medicine
    {
        if (isset($validated['medicine_id'])) {
            $medicine = Medicine::query()
                ->lockForUpdate()
                ->findOrFail($validated['medicine_id']);
            $this->ensureMedicineIdentityIsAvailable($validated, $medicine);
            $medicine->fill($this->medicineValues($validated, $medicine));

            if ($medicine->isDirty()) {
                $medicine->save();
            }

            return $medicine;
        }

        $values = $this->medicineValues($validated);
        $identityKey = MedicineIdentity::key(
            $values['medicine_name'] ?? null,
            $values['brand_name'] ?? null,
            $values['dosage'] ?? null,
        );

        return Medicine::query()->firstOrCreate(
            ['identity_key' => $identityKey],
            $values,
        );
    }

    private function ensureMedicineIdentityIsAvailable(array $validated, Medicine $medicine): void
    {
        $values = $this->medicineValues($validated, $medicine);
        $identityKey = MedicineIdentity::key(
            array_key_exists('medicine_name', $values) ? $values['medicine_name'] : $medicine->medicine_name,
            array_key_exists('brand_name', $values) ? $values['brand_name'] : $medicine->brand_name,
            array_key_exists('dosage', $values) ? $values['dosage'] : $medicine->dosage,
        );
        $currentIdentityKey = MedicineIdentity::key(
            $medicine->medicine_name,
            $medicine->brand_name,
            $medicine->dosage
        );

        if ($identityKey === $currentIdentityKey) {
            return;
        }

        $duplicateExists = Medicine::query()
            ->where('identity_key', $identityKey)
            ->whereKeyNot($medicine->getKey())
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'medicine_name' => 'This medicine identity is already registered in the shared catalog.',
            ]);
        }
    }

    private function validateMedicineAttributes(array $attributes): array
    {
        $validator = Validator::make($attributes, [
            'medicine_id' => ['sometimes', 'nullable', 'integer', 'exists:medicines,id'],
            'medicine_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dosage' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'categories_present' => ['sometimes', 'boolean'],
            'categories' => ['sometimes', 'array', 'max:20'],
            'categories.*' => ['string', 'max:255'],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requiresPrescription' => ['sometimes', 'boolean'],
            'cold_chain_required' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($attributes): void {
            if (array_key_exists('medicine_name', $attributes) && trim((string) $attributes['medicine_name']) === '') {
                $validator->errors()->add('medicine_name', 'The generic name field must contain at least one non-whitespace character.');
            }
        });

        $validated = $validator->validate();
        $validated['medicine_name'] = trim($validated['medicine_name']);

        return $validated;
    }

    private function medicineValues(array $validated, ?Medicine $existing = null): array
    {
        $values = [];

        foreach (self::MEDICINE_FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $values[$field] = $validated[$field];
            }
        }

        if (array_key_exists('categories_present', $validated) || array_key_exists('categories', $validated)) {
            $values['categories'] = $this->normalizeCategories($validated['categories'] ?? []);
            $values['category'] = $values['categories'][0] ?? null;
        } elseif (array_key_exists('category', $validated)) {
            $categories = $this->normalizeCategories([$validated['category']]);
            $optionValue = MedicineCategory::optionValue($validated['category']);
            $category = $categories[0] ?? null;

            if ($existing === null || $optionValue !== MedicineCategory::optionValue($existing->category)) {
                $values['categories'] = $categories;
                $values['category'] = $category;
            }
        }

        if ($existing === null) {
            $values += [
                'brand_name' => null,
                'dosage' => '',
                'category' => null,
                'categories' => [],
                'manufacturer' => '',
                'requiresPrescription' => false,
                'cold_chain_required' => false,
            ];
        } else {
            foreach (['dosage', 'manufacturer'] as $requiredString) {
                if (array_key_exists($requiredString, $values) && $values[$requiredString] === null) {
                    $values[$requiredString] = '';
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<int, string>  $categories
     * @return array<int, string>
     */
    private function normalizeCategories(array $categories): array
    {
        $canonicalOptions = MedicineCategory::canonicalOptions();
        $normalized = [];

        foreach ($categories as $category) {
            $category = trim($category);
            if ($category === '') {
                continue;
            }

            $optionValue = MedicineCategory::optionValue($category);
            $label = $canonicalOptions[$optionValue] ?? $category;
            $normalized[mb_strtolower($label)] = $label;
        }

        return array_values($normalized);
    }

    private function validateParLevel(int $parLevel): void
    {
        if ($parLevel < 0) {
            throw ValidationException::withMessages([
                'par_level' => 'The par level must be a non-negative integer.',
            ]);
        }
    }

    private function rejectStockFields(array $attributes): void
    {
        $errors = [];

        foreach (self::STOCK_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $errors[$field] = 'Stock fields cannot be changed here. Use the Add Stock / Receive Delivery workflow instead.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
