<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;

final class RecordSaleRequest extends PharmacyInventoryRequest
{
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $this->merge(['items' => array_values($items)]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array'],
            'items.*.inventory_item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'batch_number' => ['prohibited'],
            'inventory_batch_id' => ['prohibited'],
            'items.*.batch_number' => ['prohibited'],
            'items.*.inventory_batch_id' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $seen = [];

            foreach ((array) $this->input('items', []) as $index => $item) {
                $aggregateId = is_array($item) ? ($item['inventory_item_id'] ?? null) : null;

                if (filter_var($aggregateId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    continue;
                }

                $normalizedId = (int) $aggregateId;
                if (isset($seen[$normalizedId])) {
                    $validator->errors()->add(
                        "items.{$index}.inventory_item_id",
                        'Each medicine may appear only once in a sale.',
                    );
                }

                $seen[$normalizedId] = true;
            }
        }];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one medicine to record a sale.',
            'items.min' => 'Add at least one medicine to record a sale.',
            'items.max' => 'A sale may contain no more than 50 medicine rows.',
            'items.*.inventory_item_id.required' => 'Select a medicine for this sale row.',
            'items.*.quantity.required' => 'Enter the sold quantity for this sale row.',
            'items.*.quantity.integer' => 'The sold quantity must be a whole number.',
            'items.*.quantity.min' => 'The sold quantity must be at least 1.',
            'batch_number.prohibited' => 'Batch selection is automatic through FEFO and cannot be submitted for a basic sale.',
            'inventory_batch_id.prohibited' => 'Batch selection is automatic through FEFO and cannot be submitted for a basic sale.',
            'items.*.batch_number.prohibited' => 'Batch selection is automatic through FEFO and cannot be submitted for a basic sale.',
            'items.*.inventory_batch_id.prohibited' => 'Batch selection is automatic through FEFO and cannot be submitted for a basic sale.',
        ];
    }

    /**
     * @return list<array{inventory_item_id: int, quantity: int}>
     */
    public function saleItems(): array
    {
        return array_map(
            static fn (array $item): array => [
                'inventory_item_id' => (int) $item['inventory_item_id'],
                'quantity' => (int) $item['quantity'],
            ],
            array_values($this->validated('items')),
        );
    }

    public function notes(): ?string
    {
        $notes = trim((string) ($this->validated('notes') ?? ''));

        return $notes === '' ? null : $notes;
    }
}
