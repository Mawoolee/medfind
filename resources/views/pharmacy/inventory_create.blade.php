@extends('layouts.app')

@section('title', 'Add New Medicine')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add New Medicine</h1>
            <p class="text-sm text-gray-500 mt-1">Create the product identity only. Receive batch stock separately after saving.</p>
        </div>
        <x-back-button :href="route('pharmacy.inventory')" label="Back to Inventory" />
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded mb-4" role="alert">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 mb-5 text-sm">
        Stock starts at zero. Batch number, lot, quantity, price, supplier, and expiry belong in
        <a href="{{ route('pharmacy.receiving.create') }}" class="font-semibold underline">Add Stock / Receive Delivery</a>.
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <form id="medicine-create-form" method="POST" action="{{ route('pharmacy.inventory.store') }}">
            @csrf

            <div class="mb-5">
                <label for="medicine_id" class="block text-sm font-medium text-gray-700">Use an existing medicine master (optional)</label>
                <select id="medicine_id" name="medicine_id" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 @error('medicine_id') border-red-500 @enderror">
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
                    <input id="medicine_name" type="text" name="medicine_name" value="{{ old('medicine_name') }}" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 @error('medicine_name') border-red-500 @enderror" placeholder="e.g., Paracetamol">
                    @error('medicine_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="brand_name" class="block text-sm font-medium text-gray-700">Brand Name</label>
                    <input id="brand_name" type="text" name="brand_name" value="{{ old('brand_name') }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 @error('brand_name') border-red-500 @enderror" placeholder="e.g., Biogesic">
                    @error('brand_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="dosage" class="block text-sm font-medium text-gray-700">Dosage</label>
                    <input id="dosage" type="text" name="dosage" value="{{ old('dosage') }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 @error('dosage') border-red-500 @enderror" placeholder="e.g., 500mg">
                    @error('dosage')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select id="category" name="category" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 @error('category') border-red-500 @enderror">
                        <option value="">-- Select --</option>
                        @foreach($categoryOptions as $value => $label)
                            <option value="{{ $value }}" {{ $selectedCategory === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="manufacturer" class="block text-sm font-medium text-gray-700">Manufacturer</label>
                    <input id="manufacturer" type="text" name="manufacturer" value="{{ old('manufacturer') }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 @error('manufacturer') border-red-500 @enderror">
                    @error('manufacturer')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="par_level" class="block text-sm font-medium text-gray-700">Par Level</label>
                    <input id="par_level" type="number" name="par_level" min="0" value="{{ old('par_level', 0) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 @error('par_level') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Compared with the combined available quantity of all batches.</p>
                    @error('par_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center pt-6">
                    <input type="hidden" name="requiresPrescription" value="0">
                    <label for="requiresPrescription" class="flex items-center text-sm font-medium text-gray-700">
                        <input id="requiresPrescription" type="checkbox" name="requiresPrescription" value="1" {{ old('requiresPrescription') ? 'checked' : '' }} class="mr-2">
                        Requires prescription
                    </label>
                </div>

                <div class="flex items-center pt-6">
                    <input type="hidden" name="cold_chain_required" value="0">
                    <label for="cold_chain_required" class="flex items-center text-sm font-medium text-gray-700">
                        <input id="cold_chain_required" type="checkbox" name="cold_chain_required" value="1" {{ old('cold_chain_required') ? 'checked' : '' }} class="mr-2">
                        Cold-chain required for every batch
                    </label>
                    @error('cold_chain_required')<p class="ml-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Save Medicine</button>
                <a href="{{ route('pharmacy.inventory') }}" class="text-sm text-gray-600">Cancel</a>
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
        category: document.getElementById('category'),
        manufacturer: document.getElementById('manufacturer'),
        par_level: document.getElementById('par_level'),
        requires_prescription: document.getElementById('requiresPrescription'),
        cold_chain_required: document.getElementById('cold_chain_required'),
    };
    const initialValues = Object.fromEntries(Object.entries(fields).map(([key, field]) => [
        key,
        field.type === 'checkbox' ? field.checked : field.value,
    ]));

    function applyValues(values) {
        Object.entries(fields).forEach(([key, field]) => {
            if (field.type === 'checkbox') {
                field.checked = Boolean(values[key]);
            } else {
                field.value = values[key] ?? '';
            }
        });
    }

    selector.addEventListener('change', () => applyValues(medicineAutofill[selector.value] ?? initialValues));

    if (selector.value && medicineAutofill[selector.value]) {
        applyValues({...medicineAutofill[selector.value], ...Object.fromEntries(
            Object.entries(fields).map(([key, field]) => [key, field.type === 'checkbox' ? field.checked : field.value])
        )});
    }
});
</script>
@endsection
