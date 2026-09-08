@extends('layouts.app')

@section('title', 'Add Stock / Receive Delivery')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add Stock / Receive Delivery</h1>
            <p class="text-sm text-gray-500 mt-1">Creates a new, traceable stock batch for an existing medicine.</p>
        </div>
        <x-back-button :href="route('pharmacy.dashboard')" label="Back to Dashboard" />
    </div>

    @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($inventory->isEmpty())
        <div class="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg p-5">
            Add a medicine master before receiving stock.
            <a href="{{ route('pharmacy.inventory.create') }}" class="font-semibold underline">Add New Medicine</a>
        </div>
    @else
        <div class="bg-white rounded-lg shadow-lg p-6">
            <p class="text-sm text-gray-500 mb-5">A duplicate batch number for the same medicine will be rejected, never overwritten.</p>

            <form id="receiving-form" action="{{ route('pharmacy.receiving.store') }}" method="POST">
                @csrf

                {{-- Hidden fields that the request still accepts as nullable --}}
                <input type="hidden" name="supplier_id" value="">
                <input type="hidden" name="purchase_order" value="">

                {{-- Row 1: Medicine searchable combobox --}}
                <div class="mb-4">
                    <label for="medicine_search" class="block text-sm font-medium text-gray-700 mb-1">
                        Existing Medicine <span class="text-red-500">*</span>
                    </label>
                    <div style="position:relative">
                        <input
                            type="text"
                            id="medicine_search"
                            autocomplete="off"
                            placeholder="Type to search medicine..."
                            class="w-full border border-gray-300 rounded px-3 py-2.5 text-base"
                        >
                        <input
                            type="hidden"
                            name="items[0][inventory_item_id]"
                            id="medicine_hidden"
                            value="{{ old('items.0.inventory_item_id', $selectedInventoryId ?? '') }}"
                        >
                        <ul id="medicine_list" style="display:none;position:absolute;z-index:50;background:white;border:1px solid #d1d5db;border-radius:6px;max-height:200px;overflow-y:auto;width:100%;margin-top:2px;list-style:none;padding:0;margin-left:0;"></ul>
                    </div>
                    <p id="medicine-combo-error" class="mt-1 text-sm text-red-600" style="display:none;">Please select a medicine from the list.</p>
                </div>

                {{-- Row 2: Gen. Name + Brand Name --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gen. Name</label>
                        <input
                            type="text"
                            id="display_generic_name"
                            class="w-full border border-gray-300 rounded px-3 py-2.5 text-base"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Brand Name</label>
                        <input
                            type="text"
                            id="display_brand_name"
                            class="w-full border border-gray-300 rounded px-3 py-2.5 text-base"
                        >
                    </div>
                </div>

                {{-- Row 3: Dosage + Batch No. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dosage</label>
                        <input
                            type="text"
                            id="display_dosage"
                            class="w-full border border-gray-300 rounded px-3 py-2.5 text-base"
                        >
                    </div>
                    <div>
                        <label for="batch_number" class="block text-sm font-medium text-gray-700 mb-1">
                            Batch No. <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="batch_number"
                            name="items[0][batch_number]"
                            value="{{ old('items.0.batch_number', '') }}"
                            required
                            class="w-full border border-gray-300 rounded px-3 py-2.5 text-base"
                            placeholder="B001"
                        >
                    </div>
                </div>

                {{-- Row 4: Price + Quantity --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">
                            Price <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="price"
                            name="items[0][price]"
                            value="{{ old('items.0.price', '') }}"
                            required
                            min="0"
                            step="0.01"
                            class="w-full border border-gray-300 rounded px-3 py-2.5 text-base"
                            placeholder="0.00"
                        >
                    </div>
                    <div>
                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">
                            Quantity <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="quantity"
                            name="items[0][quantity]"
                            value="{{ old('items.0.quantity', '') }}"
                            required
                            min="1"
                            class="w-full border border-gray-300 rounded px-3 py-2.5 text-base"
                            placeholder="1"
                        >
                    </div>
                </div>

                {{-- Row 5: Expiry Date + Date Received --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="expiry_date" class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                        <input
                            type="date"
                            id="expiry_date"
                            name="items[0][expiry_date]"
                            value="{{ old('items.0.expiry_date', '') }}"
                            class="w-full border border-gray-300 rounded px-3 py-2.5 text-base"
                        >
                    </div>
                    <div>
                        <label for="received_date" class="block text-sm font-medium text-gray-700 mb-1">
                            Date Received <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            id="received_date"
                            name="items[0][received_date]"
                            value="{{ old('items.0.received_date', now()->format('Y-m-d')) }}"
                            required
                            class="w-full border border-gray-300 rounded px-3 py-2.5 text-base"
                        >
                    </div>
                </div>

                {{-- Row 6: Cold Chain + Requires Prescription --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="flex items-center gap-3 pt-1">
                        <input type="hidden" name="items[0][cold_chain]" value="0">
                        <input
                            type="checkbox"
                            id="cold_chain"
                            name="items[0][cold_chain]"
                            value="1"
                            {{ old('items.0.cold_chain') ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600"
                        >
                        <label for="cold_chain" class="text-sm font-medium text-gray-700">Cold Chain Required</label>
                    </div>
                    <div class="flex items-center gap-3 pt-1">
                        <input
                            type="checkbox"
                            id="display_requires_prescription"
                            disabled
                            tabindex="-1"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 cursor-default"
                        >
                        <label for="display_requires_prescription" class="text-sm font-medium text-gray-700 cursor-default">
                            Requires Prescription <span class="text-gray-400 font-normal text-xs">(from medicine)</span>
                        </label>
                    </div>
                </div>

                {{-- Hidden nullable field --}}
                <input type="hidden" name="items[0][lot_number]" value="">

                {{-- Buttons --}}
                <div class="flex flex-col sm:flex-row gap-3">
                    <button
                        type="submit"
                        class="w-full sm:w-auto inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg min-h-11"
                    >
                        <i class="fas fa-check mr-2"></i>Process Delivery
                    </button>
                    <a
                        href="{{ route('pharmacy.inventory') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg min-h-11"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection

@php
    $medicineOptionsJson = $inventory->map(fn($item) => [
        'id'                    => $item->id,
        'name'                  => $item->medicine->medicine_name,
        'brand'                 => $item->medicine->brand_name ?? '',
        'dosage'                => $item->medicine->dosage ?? '',
        'cold_chain_required'   => $item->medicine->cold_chain_required ? 1 : 0,
        'requires_prescription' => $item->medicine->requiresPrescription ? 1 : 0,
    ])->values();
@endphp
@push('scripts')
<script>
(() => {
    const medicineOptions = @json($medicineOptionsJson);

    const textInput   = document.getElementById('medicine_search');
    const hiddenInput = document.getElementById('medicine_hidden');
    const dropList    = document.getElementById('medicine_list');
    const errEl       = document.getElementById('medicine-combo-error');

    const displayGeneric  = document.getElementById('display_generic_name');
    const displayBrand    = document.getElementById('display_brand_name');
    const displayDosage   = document.getElementById('display_dosage');
    const displayRxCheck  = document.getElementById('display_requires_prescription');
    const coldChainBox    = document.getElementById('cold_chain');

    function fillDisplayFields(option) {
        if (option) {
            displayGeneric.value      = option.name;
            displayBrand.value        = option.brand;
            displayDosage.value       = option.dosage;
            displayRxCheck.checked    = option.requires_prescription === 1;
            if (option.cold_chain_required === 1) coldChainBox.checked = true;
        } else {
            displayGeneric.value   = '';
            displayBrand.value     = '';
            displayDosage.value    = '';
            displayRxCheck.checked = false;
        }
    }

    // Pre-fill from old() value on page load
    const oldId = hiddenInput.value;
    if (oldId) {
        const found = medicineOptions.find(m => String(m.id) === String(oldId));
        if (found) {
            textInput.value = found.name + (found.brand ? ' — ' + found.brand : '') + (found.dosage ? ' (' + found.dosage + ')' : '');
            fillDisplayFields(found);
        }
    }

    function renderList(items) {
        dropList.innerHTML = '';
        if (!items.length) {
            dropList.style.display = 'none';
            return;
        }
        items.forEach(m => {
            const li = document.createElement('li');
            let label = m.name;
            if (m.brand)  label += ' — ' + m.brand;
            if (m.dosage) label += ' (' + m.dosage + ')';
            li.textContent       = label;
            li.dataset.id        = m.id;
            li.style.cssText     = 'padding:8px 12px;cursor:pointer;font-size:0.875rem;';
            li.addEventListener('mousedown', e => {
                e.preventDefault();
                textInput.value   = label;
                hiddenInput.value = m.id;
                dropList.style.display = 'none';
                fillDisplayFields(m);
                errEl.style.display = 'none';
                textInput.classList.remove('border-red-500');
            });
            li.addEventListener('mouseover', () => li.style.background = '#f3f4f6');
            li.addEventListener('mouseout',  () => li.style.background = '');
            dropList.appendChild(li);
        });
        dropList.style.display = 'block';
    }

    textInput.addEventListener('input', () => {
        hiddenInput.value = '';
        fillDisplayFields(null);
        const q = textInput.value.trim().toLowerCase();
        renderList(q ? medicineOptions.filter(m => {
            const haystack = (m.name + ' ' + m.brand + ' ' + m.dosage).toLowerCase();
            return haystack.includes(q);
        }) : medicineOptions);
    });

    textInput.addEventListener('focus', () => {
        const q = textInput.value.trim().toLowerCase();
        renderList(q ? medicineOptions.filter(m => {
            const haystack = (m.name + ' ' + m.brand + ' ' + m.dosage).toLowerCase();
            return haystack.includes(q);
        }) : medicineOptions);
    });

    textInput.addEventListener('click', () => {
        const q = textInput.value.trim().toLowerCase();
        renderList(q ? medicineOptions.filter(m => {
            const haystack = (m.name + ' ' + m.brand + ' ' + m.dosage).toLowerCase();
            return haystack.includes(q);
        }) : medicineOptions);
    });

    textInput.addEventListener('blur', () => {
        setTimeout(() => {
            dropList.style.display = 'none';
            if (!hiddenInput.value) {
                textInput.value = '';
                fillDisplayFields(null);
            }
        }, 150);
    });

    document.addEventListener('click', e => {
        if (!textInput.contains(e.target) && !dropList.contains(e.target)) {
            dropList.style.display = 'none';
        }
    });

    // Form submit guard
    const form = document.getElementById('receiving-form');
    form.addEventListener('submit', function(e) {
        if (!hiddenInput.value) {
            e.preventDefault();
            textInput.classList.add('border-red-500');
            errEl.style.display = 'block';
            textInput.focus();
        }
    });

    textInput.addEventListener('input', function() {
        textInput.classList.remove('border-red-500');
        errEl.style.display = 'none';
    });
})();
</script>
@endpush
