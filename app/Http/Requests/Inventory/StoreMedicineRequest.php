<?php

namespace App\Http\Requests\Inventory;

final class StoreMedicineRequest extends MedicineMasterRequest
{
    // The shared medicine-master rules intentionally reject every stock field.
}
