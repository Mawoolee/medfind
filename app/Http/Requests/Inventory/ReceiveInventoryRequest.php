<?php

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Data\BatchReceiptData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

final class ReceiveInventoryRequest extends PharmacyInventoryRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $pharmacyId = $this->pharmacy()->getKey();

        return [
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'purchase_order' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array'],
            'items.*.inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')
                    ->where(fn (Builder $query) => $query->where('pharmacy_id', $pharmacyId)),
            ],
            'items.*.batch_number' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'items.*.lot_number' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'items.*.supplier_name' => ['nullable', 'string', 'max:255'],
            'items.*.expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.cold_chain' => ['sometimes', 'boolean'],
            'items.*.received_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.received_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.inventory_item_id.exists' => 'The selected medicine is not available in this pharmacy inventory catalog.',
            'items.*.batch_number.regex' => 'The batch number must contain at least one non-whitespace character.',
            'items.*.price.decimal' => 'The price must have no more than two decimal places.',
        ];
    }

    public function receiptData(int $index): BatchReceiptData
    {
        $item = $this->validated("items.{$index}");

        return new BatchReceiptData(
            batchNumber: trim($item['batch_number']),
            lotNumber: $this->nullableTrimmed($item['lot_number'] ?? null),
            quantityReceived: (int) $item['quantity'],
            price: (string) $item['price'],
            supplierName: $this->nullableTrimmed($item['supplier_name'] ?? $this->validated('supplier_name')),
            supplierId: isset($item['supplier_id'])
                ? (int) $item['supplier_id']
                : ($this->validated('supplier_id') === null ? null : (int) $this->validated('supplier_id')),
            expiryDate: $this->parseDate($item['expiry_date'] ?? null),
            coldChain: filter_var($item['cold_chain'] ?? false, FILTER_VALIDATE_BOOL),
            receivedDate: $this->parseDate($item['received_date'] ?? null),
            receivedReference: $this->nullableTrimmed(
                $item['received_reference'] ?? $this->validated('purchase_order')
            ),
            createdBy: $this->user()->getKey(),
        );
    }

    private function parseDate(?string $date): ?CarbonImmutable
    {
        return $date === null || $date === '' ? null : CarbonImmutable::createFromFormat('Y-m-d', $date);
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
