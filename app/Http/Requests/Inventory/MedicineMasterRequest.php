<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

abstract class MedicineMasterRequest extends PharmacyInventoryRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $stockFields = [
            'stockQuantity',
            'stock_quantity',
            'price',
            'batch_number',
            'lot_number',
            'quantity',
            'quantity_received',
            'current_quantity',
            'supplier_id',
            'supplier_name',
            'expiry_date',
            'cold_chain',
            'received_date',
            'received_reference',
        ];

        $rules = [
            'medicine_id' => ['nullable', 'integer', Rule::exists('medicines', 'id')],
            'medicine_name' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'dosage' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'requiresPrescription' => ['sometimes', 'boolean'],
            'cold_chain_required' => ['sometimes', 'boolean'],
            'par_level' => ['nullable', 'integer', 'min:0'],
        ];

        foreach ($stockFields as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'medicine_name.required' => 'The generic name field is required.',
            'medicine_name.regex' => 'The generic name must contain at least one non-whitespace character.',
            '*.prohibited' => 'Stock and batch fields must be submitted through Add Stock/Receive Delivery.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function medicineAttributes(): array
    {
        return collect($this->validated())
            ->only([
                'medicine_name',
                'brand_name',
                'dosage',
                'category',
                'manufacturer',
                'requiresPrescription',
                'cold_chain_required',
            ])
            ->all();
    }

    public function parLevel(): int
    {
        return (int) ($this->validated('par_level') ?? 0);
    }
}
