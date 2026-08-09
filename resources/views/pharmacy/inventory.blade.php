{{-- resources/views/pharmacy/inventory.blade.php --}}

@extends('layouts.app')

@section('title', 'Manage Inventory')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📦 Manage Inventory</h1>
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500">{{ $pharmacy->pharmacy_name ?? '' }}</span>
            <a href="{{ route('pharmacy.inventory.export') }}" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm">
                <i class="fas fa-file-csv mr-1"></i>Export CSV
            </a>
            <a href="{{ route('pharmacy.dashboard') }}" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            <!-- Filters -->
            <form method="GET" action="{{ route('pharmacy.inventory') }}" class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-3">
                <div class="md:col-span-2">
                    <input
                        id="pharmacyInventorySearch"
                        name="q"
                        value="{{ $q ?? '' }}"
                        type="text"
                        placeholder="Search by medicine name / manufacturer"
                        autocomplete="off"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none"
                    />
                </div>
                <select name="category" class="px-3 py-2 border border-gray-300 rounded">
                    <option value="">All Categories</option>
                    @foreach($categories ?? [] as $cat)
                        <option value="{{ $cat }}" {{ ($category ?? '') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
                <select name="stock" class="px-3 py-2 border border-gray-300 rounded">
                    <option value="">All Stock</option>
                    <option value="in" {{ ($stock ?? '') === 'in' ? 'selected' : '' }}>In Stock</option>
                    <option value="low" {{ ($stock ?? '') === 'low' ? 'selected' : '' }}>Below Par (Low)</option>
                    <option value="out" {{ ($stock ?? '') === 'out' ? 'selected' : '' }}>Out of Stock</option>
                    <option value="expiring" {{ ($stock ?? '') === 'expiring' ? 'selected' : '' }}>Expiring (90d)</option>
                    <option value="expired" {{ ($stock ?? '') === 'expired' ? 'selected' : '' }}>Expired</option>
                </select>
                <select name="sort" class="px-3 py-2 border border-gray-300 rounded">
                    <option value="recent" {{ ($sort ?? '') === 'recent' ? 'selected' : '' }}>Sort: Recent</option>
                    <option value="fefo" {{ ($sort ?? '') === 'fefo' ? 'selected' : '' }}>Sort: FEFO (Expiry)</option>
                    <option value="name" {{ ($sort ?? '') === 'name' ? 'selected' : '' }}>Sort: Name</option>
                    <option value="low" {{ ($sort ?? '') === 'low' ? 'selected' : '' }}>Sort: Stock (Low→High)</option>
                    <option value="high" {{ ($sort ?? '') === 'high' ? 'selected' : '' }}>Sort: Stock (High→Low)</option>
                </select>
                <div class="md:col-span-1 flex items-center gap-2">
                    <label class="flex items-center text-sm text-gray-600">
                        <input type="checkbox" name="cold_chain" value="1" {{ ($coldChain ?? '') ? 'checked' : '' }} class="mr-1"> Cold Chain
                    </label>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">Filter</button>
                </div>
            </form>

            <p id="inventorySearchResultCount" class="mt-3 text-sm text-gray-500">Showing {{ $inventory->total() }} inventory items.</p>

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
                                <th class="px-4 py-3 text-left text-gray-600">Batch / Expiry</th>
                                <th class="px-4 py-3 text-left text-gray-600">Current Stock</th>
                                <th class="px-4 py-3 text-left text-gray-600">Par</th>
                                <th class="px-4 py-3 text-left text-gray-600">Price (₱)</th>
                                <th class="px-4 py-3 text-left text-gray-600">Status</th>
                                <th class="px-4 py-3 text-left text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            @forelse($inventory as $item)
                                @php
                                    $expiryStatus = $item->expiry_status;
                                    $stockStatus = $item->stock_status;
                                @endphp
                                <tr class="border-t border-gray-200 {{ $expiryStatus === 'expired' ? 'bg-red-50' : ($expiryStatus === 'critical' ? 'bg-orange-50' : ($stockStatus === 'low' ? 'bg-yellow-50' : '')) }}">
                                    <td class="px-4 py-3 font-semibold" data-medicine-name>
                                        {{ $item->medicine->medicine_name }}
                                        @if($item->cold_chain)
                                            <span class="ml-1 text-xs text-blue-600" title="Cold chain"><i class="fas fa-snowflake"></i></span>
                                        @endif
                                        @if($item->is_controlled)
                                            <span class="ml-1 text-xs text-red-600" title="Controlled substance"><i class="fas fa-shield-halved"></i></span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3" data-medicine-dosage>{{ $item->medicine->dosage }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div>{{ $item->batch_number ?? '—' }}</div>
                                        @if($item->expiry_date)
                                            <div class="{{ $expiryStatus === 'expired' ? 'text-red-600 font-semibold' : ($expiryStatus === 'critical' ? 'text-orange-600 font-semibold' : 'text-gray-500') }}">
                                                {{ $item->expiry_date->format('M d, Y') }}
                                                @if($expiryStatus === 'expired') (EXPIRED)
                                                @elseif($expiryStatus === 'critical') ({{ $item->days_until_expiry }}d left)
                                                @elseif($expiryStatus === 'short_dated') ({{ $item->days_until_expiry }}d)
                                                @endif
                                            </div>
                                        @else
                                            <div class="text-gray-400">No expiry</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number"
                                               name="stock[{{ $item->id }}]"
                                               value="{{ $item->stockQuantity }}"
                                               min="0"
                                               class="w-20 px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $item->par_level ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <input type="number"
                                               name="price[{{ $item->id }}]"
                                               value="{{ $item->price }}"
                                               step="0.01"
                                               min="0"
                                               class="w-28 px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($expiryStatus === 'expired')
                                            <span class="px-2 py-1 rounded text-xs bg-red-600 text-white">Expired</span>
                                        @elseif($stockStatus === 'out_of_stock')
                                            <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700">Out of Stock</span>
                                        @elseif($stockStatus === 'low')
                                            <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-700">Low Stock</span>
                                        @else
                                            <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">In Stock</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <button type="submit" name="update_id" value="{{ $item->id }}"
                                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition duration-200">
                                                <i class="fas fa-save mr-1"></i>Update
                                            </button>
                                            <a href="{{ route('pharmacy.inventory.edit', $item->id) }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
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
