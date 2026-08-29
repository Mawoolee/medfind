<?php

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Data\BatchMetadataData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class UpdateInventoryBatchRequest extends PharmacyInventoryRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $this->batch();

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'batch_number' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'lot_number' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'cold_chain' => ['sometimes', 'boolean'],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'received_reference' => ['nullable', 'string', 'max:255'],
            'quantity_received' => ['prohibited'],
            'current_quantity' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'batch_number.regex' => 'The batch number must contain at least one non-whitespace character.',
            'price.decimal' => 'The price must have no more than two decimal places.',
            'quantity_received.prohibited' => 'Quantity received cannot be changed by editing batch metadata.',
            'current_quantity.prohibited' => 'Use the batch quantity correction action to change current quantity.',
        ];
    }

    public function metadataData(): BatchMetadataData
    {
        $data = $this->validated();

        return new BatchMetadataData(
            batchNumber: trim($data['batch_number']),
            lotNumber: $this->nullableTrimmed($data['lot_number'] ?? null),
            price: (string) $data['price'],
            supplierName: $this->nullableTrimmed($data['supplier_name'] ?? null),
            supplierId: isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            expiryDate: $this->parseDate($data['expiry_date'] ?? null),
            coldChain: filter_var($data['cold_chain'] ?? false, FILTER_VALIDATE_BOOL),
            receivedDate: $this->parseDate($data['received_date']),
            receivedReference: $this->nullableTrimmed($data['received_reference'] ?? null),
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
