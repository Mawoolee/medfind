<?php

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Data\BatchReceiptData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class AdjustInventoryRequest extends PharmacyInventoryRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $this->aggregate();

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $increase = fn (): bool => (int) $this->input('target_quantity', -1) > (int) $this->aggregate()->stockQuantity;

        return [
            'target_quantity' => ['required', 'integer', 'min:0'],
            'correction_reason' => [Rule::requiredIf($increase), 'nullable', 'string', 'max:1000', 'regex:/\S/u'],
            'batch_number' => [Rule::requiredIf($increase), 'nullable', 'string', 'max:255', 'regex:/\S/u'],
            'lot_number' => ['nullable', 'string', 'max:255'],
            'price' => [Rule::requiredIf($increase), 'nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'cold_chain' => ['sometimes', 'boolean'],
            'received_date' => [Rule::requiredIf($increase), 'nullable', 'date_format:Y-m-d'],
            'received_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'correction_reason.required' => 'A correction reason is required when increasing stock.',
            'correction_reason.regex' => 'The correction reason must contain at least one non-whitespace character.',
            'batch_number.required' => 'A batch number is required when increasing stock.',
            'batch_number.regex' => 'The batch number must contain at least one non-whitespace character.',
            'price.required' => 'A price is required when increasing stock.',
            'price.decimal' => 'The price must have no more than two decimal places.',
            'received_date.required' => 'A received date is required when increasing stock.',
        ];
    }

    public function increaseBatchData(): ?BatchReceiptData
    {
        $data = $this->validated();
        $increase = (int) $data['target_quantity'] - (int) $this->aggregate()->stockQuantity;

        if ($increase <= 0) {
            return null;
        }

        return new BatchReceiptData(
            batchNumber: trim($data['batch_number']),
            lotNumber: $this->nullableTrimmed($data['lot_number'] ?? null),
            quantityReceived: $increase,
            price: (string) $data['price'],
            supplierName: $this->nullableTrimmed($data['supplier_name'] ?? null),
            supplierId: isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            expiryDate: $this->parseDate($data['expiry_date'] ?? null),
            coldChain: filter_var($data['cold_chain'] ?? false, FILTER_VALIDATE_BOOL),
            receivedDate: $this->parseDate($data['received_date']),
            receivedReference: $this->nullableTrimmed($data['received_reference'] ?? null),
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
