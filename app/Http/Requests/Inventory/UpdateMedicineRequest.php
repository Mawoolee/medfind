<?php

namespace App\Http\Requests\Inventory;

final class UpdateMedicineRequest extends MedicineMasterRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $this->aggregate();

        return true;
    }
}
