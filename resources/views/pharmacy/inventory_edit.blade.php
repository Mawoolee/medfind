@extends('layouts.app')

@section('title', 'Edit Medicine')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Edit Medicine</h1>
            <p class="text-sm text-gray-500 mt-1">This page changes product identity and par level only. Existing batches are preserved.</p>
        </div>
        <x-back-button :href="route('pharmacy.inventory')" label="Back to Inventory" />
    </div>

    @if(session('error'))
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
            <div><p class="text-sm text-gray-500">Available Stock</p><p class="text-xl font-semibold">{{ $item->available_stock }}</p></div>
            <div><p class="text-sm text-gray-500">Nearest Valid Expiry</p><p class="font-medium">{{ $item->nearest_valid_expiry?->format('M d, Y') ?? '—' }}</p></div>
            <div class="flex flex-wrap items-center gap-2 md:justify-end">
                <a href="{{ route('pharmacy.inventory.batches', ['inventory_item_id' => $item->id]) }}" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-2 min-h-11 rounded text-sm">View Batches</a>
                <a href="{{ route('pharmacy.receiving.create', ['inventory_item_id' => $item->id]) }}" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-3 py-2 min-h-11 rounded text-sm">Add Stock</a>
            </div>
        </div>

        <form method="POST" action="{{ route('pharmacy.inventory.update', $item->id) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="medicine_name" class="block text-sm font-medium text-gray-700">Generic Name <span class="text-red-500">*</span></label>
                    <input id="medicine_name" type="text" name="medicine_name" required value="{{ old('medicine_name', $item->medicine->medicine_name) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
                </div>
                <div>
                    <label for="brand_name" class="block text-sm font-medium text-gray-700">Brand Name</label>
                    <input id="brand_name" type="text" name="brand_name" value="{{ old('brand_name', $item->medicine->brand_name) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
                </div>
                <div>
                    <label for="dosage" class="block text-sm font-medium text-gray-700">Dosage</label>
                    <input id="dosage" type="text" name="dosage" value="{{ old('dosage', $item->medicine->dosage) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
                </div>
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select id="category" name="category" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
                        <option value="">-- Select --</option>
                        @foreach($categoryOptions as $value => $label)
                            <option value="{{ $value }}" {{ $selectedCategory === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="manufacturer" class="block text-sm font-medium text-gray-700">Manufacturer</label>
                    <input id="manufacturer" type="text" name="manufacturer" value="{{ old('manufacturer', $item->medicine->manufacturer) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
                </div>
                <div>
                    <label for="par_level" class="block text-sm font-medium text-gray-700">Par Level</label>
                    <input id="par_level" type="number" name="par_level" min="0" value="{{ old('par_level', $item->par_level) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
                    <p class="mt-1 text-xs text-gray-500">Compared against total available stock from all batches.</p>
                </div>
                <div class="flex items-center pt-6">
                    <input type="hidden" name="requiresPrescription" value="0">
                    <label class="flex items-center text-sm font-medium text-gray-700">
                        <input type="checkbox" name="requiresPrescription" value="1" {{ old('requiresPrescription', $item->medicine->requiresPrescription) ? 'checked' : '' }} class="mr-2"> Requires prescription
                    </label>
                </div>
                <div class="flex items-center pt-6">
                    <input type="hidden" name="cold_chain_required" value="0">
                    <label class="flex items-center text-sm font-medium text-gray-700">
                        <input type="checkbox" name="cold_chain_required" value="1" {{ old('cold_chain_required', $item->medicine->cold_chain_required) ? 'checked' : '' }} class="mr-2"> Cold-chain required for every batch
                    </label>
                </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3">
                <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 sm:py-2 rounded min-h-11">Save Medicine Details</button>
                <a href="{{ route('pharmacy.inventory') }}" class="text-center sm:text-left text-sm text-gray-600">Cancel</a>
                <button type="button" onclick="if(confirm('Remove this medicine from the pharmacy catalog? Items with stock history cannot be deleted.')) document.getElementById('delete-form').submit()" class="w-full sm:w-auto sm:ml-auto bg-red-600 hover:bg-red-700 text-white px-3 py-3 sm:py-2 rounded text-sm min-h-11">Remove Medicine</button>
            </div>
        </form>

        <form id="delete-form" method="POST" action="{{ route('pharmacy.inventory.destroy', $item->id) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>
@endsection
