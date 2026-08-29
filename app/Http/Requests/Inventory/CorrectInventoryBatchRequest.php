<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;

final class CorrectInventoryBatchRequest extends PharmacyInventoryRequest
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
            'target_quantity' => ['required', 'integer', 'min:0'],
            'correction_reason' => ['required', 'string', 'max:1000', 'regex:/\S/u'],
        ];
    }

    public function messages(): array
    {
        return [
            'correction_reason.regex' => 'The correction reason must contain at least one non-whitespace character.',
        ];
    }

    public function targetQuantity(): int
    {
        return (int) $this->validated('target_quantity');
    }

    public function correctionReason(): string
    {
        return trim($this->validated('correction_reason'));
    }
}
