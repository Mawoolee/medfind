{{-- resources/views/pharmacy/inventory.blade.php --}}

@extends('layouts.app')

@section('title', 'Manage Inventory')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📦 Manage Inventory</h1>
        <div>
            <span class="text-sm text-gray-500 mr-4">{{ $pharmacy->pharmacy_name ?? '' }}</span>
            <a href="{{ route('pharmacy.dashboard') }}" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            <div class="mb-6">
                <form method="GET" action="{{ route('pharmacy.inventory') }}" class="inventory-search-wrapper relative max-w-xl mx-auto">
                    <div class="flex">
                        <input
                            id="pharmacyInventorySearch"
                            name="q"
                            value="{{ $q ?? '' }}"
                            type="text"
                            placeholder="Search inventory by medicine name"
                            autocomplete="off"
                            class="w-full px-3 py-2 border border-gray-300 rounded-l focus:outline-none"
                        />
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r text-sm">
                            Search
                        </button>
                    </div>
                </form>
                <p id="inventorySearchResultCount" class="mt-3 text-sm text-gray-500">Showing {{ $inventory->total() }} inventory items.</p>
            </div>
            <script>
                window.inventoryMedicineNames = @json($inventoryMedicineNames ?? []);
            </script>

            <form action="{{ route('pharmacy.inventory.bulk-update') }}" method="POST">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-gray-600">Medicine</th>
                                <th class="px-4 py-3 text-left text-gray-600">Dosage</th>
                                <th class="px-4 py-3 text-left text-gray-600">Manufacturer</th>
                                <th class="px-4 py-3 text-left text-gray-600">Current Stock</th>
                                <th class="px-4 py-3 text-left text-gray-600">Price (₱)</th>
                                <th class="px-4 py-3 text-left text-gray-600">Status</th>
                                <th class="px-4 py-3 text-left text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            @forelse($inventory as $item)
                                <tr class="border-t border-gray-200">
                                        <td class="px-4 py-3 font-semibold" data-medicine-name>{{ $item->medicine->medicine_name }}</td>
                                        <td class="px-4 py-3" data-medicine-dosage>{{ $item->medicine->dosage }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $item->medicine->manufacturer }}</td>
                                    <td class="px-4 py-3">
                                        <input type="number" 
                                               name="stock[{{ $item->id }}]" 
                                               value="{{ $item->stockQuantity }}" 
                                               min="0"
                                               class="w-20 px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" 
                                               name="price[{{ $item->id }}]" 
                                               value="{{ $item->price }}" 
                                               step="0.01"
                                               min="0"
                                               class="w-28 px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded text-xs {{ $item->stockQuantity > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $item->stockQuantity > 0 ? 'In Stock' : 'Out of Stock' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="submit" name="update_id" value="{{ $item->id }}" 
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition duration-200">
                                            <i class="fas fa-save mr-1"></i>Update
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-box-open text-4xl block mb-2"></i>
                                        No inventory items found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($inventory->total() > 0)
                    <div class="mt-6 flex justify-between items-center">
                        <p class="text-sm text-gray-500">Total: {{ $inventory->total() }} medicines</p>
                        <div class="flex items-center space-x-4">
                            <a href="{{ route('pharmacy.inventory.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Add Medicine</a>
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i>Save All Changes
                            </button>
                        </div>
                    </div>

                    <div class="mt-4">
                        {{ $inventory->links() }}
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection