@extends('layouts.app')

@section('title', 'Add New Medicine')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add New Medicine</h1>
            <p class="text-sm text-gray-500 mt-1">Create the product identity only. Receive batch stock separately after saving.</p>
        </div>
        <x-back-button :href="route('pharmacy.dashboard')" label="Back to Dashboard" />
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-xl p-4 mb-5 text-sm">
        Stock starts at zero. Batch number, lot, quantity, price, supplier, and expiry belong in
        <a href="{{ route('pharmacy.receiving.create') }}" class="font-semibold underline">Add Stock / Receive Delivery</a>.
    </div>

    <div class="bg-white p-6 rounded-xl shadow-lg">
        <form id="medicine-create-form" method="POST" action="{{ route('pharmacy.inventory.store') }}">
            @csrf

            <div class="mb-5">
                <label for="medicine_id" class="block text-sm font-medium text-gray-700">Use an existing medicine master (optional)</label>
                <select id="medicine_id" name="medicine_id" class="mt-1 block w-full border border-gray-300 rounded-xl px-3 py-2.5 text-base @error('medicine_id') border-red-500 @enderror">
                    <option value="">-- Create a new medicine master --</option>
                    @foreach($medicines as $medicine)
                        <option value="{{ $medicine->id }}" {{ (string) old('medicine_id') === (string) $medicine->id ? 'selected' : '' }}>
                            {{ $medicine->medicine_name }}@if($medicine->brand_name) — {{ $medicine->brand_name }}@endif @if($medicine->dosage) ({{ $medicine->dosage }})@endif
                        </option>
                    @endforeach
                </select>
                @error('medicine_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="medicine_name" class="block text-sm font-medium text-gray-700">Generic Name <span class="text-red-500">*</span></label>
                    <input id="medicine_name" type="text" name="medicine_name" value="{{ old('medicine_name') }}" required class="mt-1 block w-full border border-gray-300 rounded-xl px-3 py-2.5 text-base @error('medicine_name') border-red-500 @enderror" placeholder="e.g., Paracetamol">
                    @error('medicine_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="brand_name" class="block text-sm font-medium text-gray-700">Brand Name</label>
                    <input id="brand_name" type="text" name="brand_name" value="{{ old('brand_name') }}" class="mt-1 block w-full border border-gray-300 rounded-xl px-3 py-2.5 text-base @error('brand_name') border-red-500 @enderror" placeholder="e.g., Biogesic">
                    @error('brand_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="dosage" class="block text-sm font-medium text-gray-700">Dosage</label>
                    <input id="dosage" type="text" name="dosage" value="{{ old('dosage') }}" class="mt-1 block w-full border border-gray-300 rounded-xl px-3 py-2.5 text-base @error('dosage') border-red-500 @enderror" placeholder="e.g., 500mg">
                    @error('dosage')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Categories</label>
                    <input type="hidden" name="categories_present" value="1">
                    <details id="medicine-category-picker" class="relative mt-1">
                        <summary id="medicine-category-summary" class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-base">
                            <span class="min-w-0 flex-1 break-words">Select all that apply</span>
                            <i class="fas fa-chevron-down text-xs text-gray-500" aria-hidden="true"></i>
                        </summary>
                        <div class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
                            <div id="medicine-category-options">
                                @foreach($categoryOptions as $value => $label)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-blue-50">
                                        <input type="checkbox" name="categories[]" value="{{ $value }}" class="medicine-category-option rounded border-gray-300 text-blue-600 focus:ring-blue-500" {{ in_array($value, $selectedCategories, true) ? 'checked' : '' }}>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-2 border-t border-gray-100 p-2">
                                <label for="custom-category-input" class="block text-xs font-medium text-gray-600">Add a custom category</label>
                                <div class="mt-1 flex gap-2">
                                    <input id="custom-category-input" type="text" maxlength="255" class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Type a category">
                                    <button id="add-custom-category" type="button" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Add</button>
                                </div>
                            </div>
                        </div>
                    </details>
                    @error('categories')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('categories.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="manufacturer" class="block text-sm font-medium text-gray-700">Manufacturer</label>
                    <input id="manufacturer" type="text" name="manufacturer" value="{{ old('manufacturer') }}" class="mt-1 block w-full border border-gray-300 rounded-xl px-3 py-2.5 text-base @error('manufacturer') border-red-500 @enderror">
                    @error('manufacturer')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="par_level" class="block text-sm font-medium text-gray-700">Par Level</label>
                    <input id="par_level" type="number" name="par_level" min="0" value="{{ old('par_level', 0) }}" class="mt-1 block w-full border border-gray-300 rounded-xl px-3 py-2.5 text-base @error('par_level') border-red-500 @enderror">
                    @error('par_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div class="flex items-center gap-3 pt-1">
                        <input type="hidden" name="cold_chain_required" value="0">
                        <input
                            type="checkbox"
                            id="cold_chain_required"
                            name="cold_chain_required" value="1" {{ old('cold_chain_required') ? 'checked' : '' }}
                            class="h-4 w-4 rounded-sm border-gray-300 text-blue-600"
                        >
                        <label for="cold_chain_required" class="text-sm font-medium text-gray-700">Cold Chain Required</label>
                        @error('cold_chain_required')<p class="ml-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex items-center gap-3 pt-1">
                        <input type="hidden" name="requiresPrescription" value="0">
                        <input
                            type="checkbox"
                            id="requiresPrescription"
                            name="requiresPrescription"
                            value="1"
                            {{ old('requiresPrescription') ? 'checked' : '' }}
                            class="h-4 w-4 rounded-sm border-gray-300 text-blue-600"
                        >
                        <label for="requiresPrescription" class="text-sm font-medium text-gray-700">
                            Requires Prescription
                            <span class="text-gray-400 font-normal text-xs ml-1">(from medicine)</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row sm:items-center gap-3">
                <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 sm:py-2 rounded-xl min-h-11">Save Medicine</button>
                <a href="{{ route('pharmacy.inventory') }}" class="text-center sm:text-left text-sm text-gray-600">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const medicineAutofill = {{ Illuminate\Support\Js::from($medicineAutofill ?? []) }};
    const selector = document.getElementById('medicine_id');
    const fields = {
        medicine_name: document.getElementById('medicine_name'),
        brand_name: document.getElementById('brand_name'),
        dosage: document.getElementById('dosage'),
        manufacturer: document.getElementById('manufacturer'),
        par_level: document.getElementById('par_level'),
        requires_prescription: document.getElementById('requiresPrescription'),
        cold_chain_required: document.getElementById('cold_chain_required'),
    };
    const categoryOptions = document.getElementById('medicine-category-options');
    const categorySummary = document.getElementById('medicine-category-summary');
    const categoryCheckboxes = () => Array.from(document.querySelectorAll('.medicine-category-option'));
    const customCategoryInput = document.getElementById('custom-category-input');
    const addCustomCategoryButton = document.getElementById('add-custom-category');
    const categoryPlaceholder = 'Select all that apply';

    function updateCategorySummary() {
        const labels = categoryCheckboxes()
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.parentElement.querySelector('span').textContent.trim());
        categorySummary.querySelector('span').textContent = labels.length ? labels.join(', ') : categoryPlaceholder;
    }

    function addCustomCategory() {
        const value = customCategoryInput.value.trim();
        if (!value) return;

        const existing = categoryCheckboxes().find(checkbox =>
            checkbox.value.toLocaleLowerCase() === value.toLocaleLowerCase()
        );
        if (existing) {
            existing.checked = true;
        } else {
            const label = document.createElement('label');
            label.className = 'flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-blue-50';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'categories[]';
            checkbox.value = value;
            checkbox.checked = true;
            checkbox.className = 'medicine-category-option rounded border-gray-300 text-blue-600 focus:ring-blue-500';
            checkbox.addEventListener('change', updateCategorySummary);
            const text = document.createElement('span');
            text.textContent = value;
            label.append(checkbox, text);
            categoryOptions.append(label);
        }

        customCategoryInput.value = '';
        updateCategorySummary();
    }

    const initialValues = Object.fromEntries(Object.entries(fields).map(([key, field]) => [
        key,
        field.type === 'checkbox' ? field.checked : field.value,
    ]));
    initialValues.categories = categoryCheckboxes().filter(checkbox => checkbox.checked).map(checkbox => checkbox.value);

    function applyValues(values) {
        Object.entries(fields).forEach(([key, field]) => {
            if (field.type === 'checkbox') {
                field.checked = Boolean(values[key]);
            } else {
                field.value = values[key] ?? '';
            }
        });
        const selected = values.categories ?? (values.category ? [values.category] : []);
        categoryCheckboxes().forEach(checkbox => {
            checkbox.checked = selected.includes(checkbox.value);
        });
        updateCategorySummary();
    }

    selector.addEventListener('change', () => applyValues(medicineAutofill[selector.value] ?? initialValues));
    categoryCheckboxes().forEach(checkbox => checkbox.addEventListener('change', updateCategorySummary));
    addCustomCategoryButton.addEventListener('click', addCustomCategory);
    customCategoryInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addCustomCategory();
        }
    });
    updateCategorySummary();

    if (selector.value && medicineAutofill[selector.value]) {
        applyValues(medicineAutofill[selector.value]);
    }
});
</script>
@endsection
