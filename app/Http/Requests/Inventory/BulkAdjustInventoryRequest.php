<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class BulkAdjustInventoryRequest extends PharmacyInventoryRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $pharmacyId = $this->pharmacy()->getKey();

        return [
            'adjustments' => ['required', 'array', 'min:1'],
            'adjustments.*' => ['required', 'array'],
            'adjustments.*.inventory_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('inventory_items', 'id')
                    ->where(fn (Builder $query) => $query->where('pharmacy_id', $pharmacyId)),
            ],
            'adjustments.*.target_quantity' => ['required', 'integer', 'min:0'],
            'adjustments.*.correction_reason' => ['nullable', 'string', 'max:1000', 'regex:/\S/u'],
            'adjustments.*.batch_number' => ['nullable', 'string', 'max:255', 'regex:/\S/u'],
            'adjustments.*.lot_number' => ['nullable', 'string', 'max:255'],
            'adjustments.*.price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'adjustments.*.supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'adjustments.*.supplier_name' => ['nullable', 'string', 'max:255'],
            'adjustments.*.expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'adjustments.*.cold_chain' => ['sometimes', 'boolean'],
            'adjustments.*.received_date' => ['nullable', 'date_format:Y-m-d'],
            'adjustments.*.received_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $adjustments = $this->input('adjustments', []);
            $ids = collect($adjustments)->pluck('inventory_item_id')->filter()->unique();
            $aggregates = $this->pharmacy()->inventory()->whereKey($ids)->get()->keyBy('id');

            foreach ($adjustments as $index => $adjustment) {
                $aggregate = $aggregates->get($adjustment['inventory_item_id'] ?? null);

                if ($aggregate === null || ! isset($adjustment['target_quantity'])) {
                    continue;
                }

                if ((int) $adjustment['target_quantity'] <= (int) $aggregate->stockQuantity) {
                    continue;
                }

                foreach ([
                    'batch_number' => 'A batch number is required when increasing stock.',
                    'price' => 'A price is required when increasing stock.',
                    'received_date' => 'A received date is required when increasing stock.',
                    'correction_reason' => 'A correction reason is required when increasing stock.',
                ] as $field => $message) {
                    if (blank(Arr::get($adjustment, $field))) {
                        $validator->errors()->add("adjustments.{$index}.{$field}", $message);
                    }
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'adjustments.*.inventory_item_id.exists' => 'The selected medicine is not available in this pharmacy inventory catalog.',
            'adjustments.*.correction_reason.regex' => 'The correction reason must contain at least one non-whitespace character.',
            'adjustments.*.batch_number.regex' => 'The batch number must contain at least one non-whitespace character.',
            'adjustments.*.price.decimal' => 'The price must have no more than two decimal places.',
        ];
    }
}
