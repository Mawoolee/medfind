@extends('layouts.app')

@section('title', 'Add Inventory Item')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white p-6 rounded shadow">
        <h2 class="text-xl font-semibold mb-4">Add Inventory Item</h2>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                Please correct the highlighted fields and try again.
            </div>
        @endif

        <form id="inventory-create-form" method="POST" action="{{ route('pharmacy.inventory.store') }}">
            @csrf

            <div class="mb-4">
                <label for="medicine_id" class="block text-sm font-medium text-gray-700">Select existing medicine</label>
                <select id="medicine_id" name="medicine_id" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('medicine_id') border-red-500 @enderror">
                    <option value="">-- Choose --</option>
                    @foreach($medicines as $med)
                        <option value="{{ $med->id }}" {{ (string) old('medicine_id') === (string) $med->id ? 'selected' : '' }}>
                            {{ $med->medicine_name }}@if($med->brand_name) — {{ $med->brand_name }}@endif @if($med->dosage) ({{ $med->dosage }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('medicine_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="medicine_name" class="block text-sm font-medium text-gray-700">Generic Name</label>
                    <input id="medicine_name" type="text" name="medicine_name" value="{{ old('medicine_name') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('medicine_name') border-red-500 @enderror" placeholder="e.g., Paracetamol" />
                    @error('medicine_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="brand_name" class="block text-sm font-medium text-gray-700">Brand Name</label>
                    <input id="brand_name" type="text" name="brand_name" value="{{ old('brand_name') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('brand_name') border-red-500 @enderror" />
                    @error('brand_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="dosage" class="block text-sm font-medium text-gray-700">Dosage</label>
                    <input id="dosage" type="text" name="dosage" value="{{ old('dosage') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('dosage') border-red-500 @enderror" />
                    @error('dosage')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="batch_number" class="block text-sm font-medium text-gray-700">Batch Number</label>
                    <input id="batch_number" type="text" name="batch_number" value="{{ old('batch_number') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('batch_number') border-red-500 @enderror" />
                    @error('batch_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="lot_number" class="block text-sm font-medium text-gray-700">Lot Number</label>
                    <input id="lot_number" type="text" name="lot_number" value="{{ old('lot_number') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('lot_number') border-red-500 @enderror" />
                    @error('lot_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700">Price (₱)</label>
                    <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('price') border-red-500 @enderror" required />
                    @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="stockQuantity" class="block text-sm font-medium text-gray-700">Initial Stocks</label>
                    <input id="stockQuantity" type="number" name="stockQuantity" min="0" value="{{ old('stockQuantity', 0) }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('stockQuantity') border-red-500 @enderror" required />
                    @error('stockQuantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="par_level" class="block text-sm font-medium text-gray-700">Par level</label>
                    <input id="par_level" type="number" name="par_level" min="0" value="{{ old('par_level', 0) }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('par_level') border-red-500 @enderror" />
                    @error('par_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select id="category" name="category" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('category') border-red-500 @enderror">
                        <option value="">-- Select --</option>
                        @foreach([
                            'analgesic' => 'Analgesic',
                            'antibiotic' => 'Antibiotic',
                            'antidiarrheal' => 'Antidiarrheal',
                            'antihistamine' => 'Antihistamine',
                            'nsaid' => 'NSAID',
                            'controlled' => 'Controlled',
                            'vitamin' => 'Vitamin',
                            'supplement' => 'Supplement',
                            'other' => 'Other',
                        ] as $value => $label)
                            <option value="{{ $value }}" {{ old('category') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="supplier_name" class="block text-sm font-medium text-gray-700">Supplier</label>
                    <input id="supplier_name" type="text" name="supplier_name" value="{{ old('supplier_name') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('supplier_name') border-red-500 @enderror" />
                    @error('supplier_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="manufacturer" class="block text-sm font-medium text-gray-700">Manufacturer</label>
                    <input id="manufacturer" type="text" name="manufacturer" value="{{ old('manufacturer') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('manufacturer') border-red-500 @enderror" />
                    @error('manufacturer')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expiry date</label>
                    <input id="expiry_date" type="date" name="expiry_date" value="{{ old('expiry_date') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2 @error('expiry_date') border-red-500 @enderror" />
                    @error('expiry_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-end">
                    <label for="cold_chain" class="flex items-center text-sm font-medium text-gray-700">
                        <input id="cold_chain" type="checkbox" name="cold_chain" value="1" {{ old('cold_chain') ? 'checked' : '' }} class="mr-2"> Cold Chain
                    </label>
                    @error('cold_chain')<p class="ml-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Add to Inventory</button>
                <a href="{{ route('pharmacy.inventory') }}" class="ml-2 text-sm text-gray-600">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const medicineAutofill = {{ Illuminate\Support\Js::from($medicineAutofill ?? []) }};
        const medicineSelector = document.getElementById('medicine_id');
        const fields = {
            generic_name: document.getElementById('medicine_name'),
            brand_name: document.getElementById('brand_name'),
            dosage: document.getElementById('dosage'),
            batch_number: document.getElementById('batch_number'),
            lot_number: document.getElementById('lot_number'),
            price: document.getElementById('price'),
            stock_quantity: document.getElementById('stockQuantity'),
            par_level: document.getElementById('par_level'),
            category: document.getElementById('category'),
            supplier_name: document.getElementById('supplier_name'),
            manufacturer: document.getElementById('manufacturer'),
            expiry_date: document.getElementById('expiry_date'),
            cold_chain: document.getElementById('cold_chain'),
        };

        const initialValues = Object.fromEntries(Object.entries(fields).map(([key, field]) => [
            key,
            field.type === 'checkbox' ? field.checked : field.value,
        ]));

        const applyValues = (values) => {
            Object.entries(fields).forEach(([key, field]) => {
                if (field.type === 'checkbox') {
                    field.checked = Boolean(values[key]);
                    return;
                }

                field.value = values[key] ?? '';
            });
        };

        medicineSelector.addEventListener('change', () => {
            const selectedMedicine = medicineAutofill[medicineSelector.value];
            applyValues(selectedMedicine ?? initialValues);
        });
    });
</script>
@endsection
