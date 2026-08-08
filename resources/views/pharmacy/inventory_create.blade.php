@extends('layouts.app')

@section('title', 'Add Inventory Item')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white p-6 rounded shadow">
        <h2 class="text-xl font-semibold mb-4">Add Inventory Item</h2>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('pharmacy.inventory.store') }}">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Select existing medicine</label>
                    <select name="medicine_id" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2">
                        <option value="">-- Choose --</option>
                        @foreach($medicines as $med)
                            <option value="{{ $med->id }}">{{ $med->medicine_name }} @if($med->dosage) ({{ $med->dosage }}) @endif</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Or enter new medicine name</label>
                    <input type="text" name="medicine_name" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" placeholder="e.g., Paracetamol" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Dosage</label>
                    <input type="text" name="dosage" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Manufacturer</label>
                    <input type="text" name="manufacturer" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Price (₱)</label>
                    <input type="number" step="0.01" name="price" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" required />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Initial Stock</label>
                    <input type="number" name="stockQuantity" min="0" class="mt-1 block w-full border border-gray-300 rounded px-2 py-2" required />
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Add to Inventory</button>
                <a href="{{ route('pharmacy.inventory') }}" class="ml-2 text-sm text-gray-600">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
