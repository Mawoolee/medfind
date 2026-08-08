@extends('layouts.app')

@section('title', 'Edit Inventory Item')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white p-6 rounded shadow">
        <h2 class="text-xl font-semibold mb-4">Edit Inventory Item</h2>

        <form method="POST" action="{{ route('pharmacy.inventory.update', $item->id) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Medicine</label>
                    <div class="mt-1">{{ $item->medicine->medicine_name }} @if($item->medicine->dosage) ({{ $item->medicine->dosage }}) @endif</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Manufacturer</label>
                    <div class="mt-1">{{ $item->medicine->manufacturer }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Price (₱)</label>
                    <input type="number" step="0.01" name="price" value="{{ $item->price }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" required />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                    <input type="number" name="stockQuantity" value="{{ $item->stockQuantity }}" min="0" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" required />
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save Changes</button>
                <a href="{{ route('pharmacy.inventory') }}" class="ml-2 text-sm text-gray-600">Cancel</a>
                <button type="button" onclick="if(confirm('Delete this item?')) { document.getElementById('delete-form').submit(); }" class="ml-4 bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded">Delete</button>
            </div>
        </form>

        <form id="delete-form" method="POST" action="{{ route('pharmacy.inventory.destroy', $item->id) }}">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>
@endsection
