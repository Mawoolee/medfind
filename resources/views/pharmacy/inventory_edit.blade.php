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

                <div>
                    <label class="block text-sm font-medium text-gray-700">Par Level (min stock)</label>
                    <input type="number" name="par_level" min="0" value="{{ $item->par_level ?? 0 }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Batch Number</label>
                    <input type="text" name="batch_number" value="{{ old('batch_number', $item->batch_number) }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Lot Number</label>
                    <input type="text" name="lot_number" value="{{ old('lot_number', $item->lot_number) }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Expiry Date</label>
                    <input type="date" name="expiry_date" value="{{ optional($item->expiry_date)->format('Y-m-d') }}" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" />
                </div>

                <div class="flex items-end">
                    <label class="flex items-center text-sm font-medium text-gray-700">
                        <input type="checkbox" name="cold_chain" value="1" {{ $item->cold_chain ? 'checked' : '' }} class="mr-2"> Cold Chain
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Supplier</label>
                    <select name="supplier_id" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2">
                        <option value="">-- Select --</option>
                        @foreach($suppliers ?? [] as $sp)
                            <option value="{{ $sp->id }}" {{ $item->supplier_id == $sp->id ? 'selected' : '' }}>{{ $sp->name }}</option>
                        @endforeach
                    </select>
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
