@extends('layouts.app')

@section('title', 'Manage Inventory')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Manage Inventory</h1>
            <p class="text-sm text-gray-500 mt-1">One row per medicine. Available stock is combined from all non-expired batches.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('pharmacy.inventory.create') }}" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 min-h-11 rounded text-sm"><i class="fas fa-plus mr-1"></i>Add New Medicine</a>
            <a href="{{ route('pharmacy.receiving.create') }}" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-3 py-2 min-h-11 rounded text-sm"><i class="fas fa-truck-ramp-box mr-1"></i>Add Stock</a>
            <a href="{{ route('pharmacy.inventory.batches') }}" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 min-h-11 rounded text-sm"><i class="fas fa-layer-group mr-1"></i>View Stock Batches</a>
            <a href="{{ route('pharmacy.inventory.export') }}" class="inline-flex items-center bg-gray-700 hover:bg-gray-800 text-white px-3 py-2 min-h-11 rounded text-sm"><i class="fas fa-file-csv mr-1"></i>Export CSV</a>
            <x-back-button :href="route('pharmacy.dashboard')" label="Back to Pharmacy Dashboard" />
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <form method="GET" action="{{ route('pharmacy.inventory') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <div class="md:col-span-2">
                    <input id="pharmacyInventorySearch" name="q" value="{{ $q }}" type="text" placeholder="Medicine, brand, or manufacturer" autocomplete="off" class="w-full px-3 py-2.5 border border-gray-300 rounded text-base focus:outline-none">
                </div>
                <select name="category" class="px-3 py-2.5 border border-gray-300 rounded text-base">
                    <option value="">All Categories</option>
                    @foreach($categoryOptions as $value => $label)
                        <option value="{{ $value }}" {{ $category === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="stock" class="px-3 py-2.5 border border-gray-300 rounded text-base">
                    <option value="">All Stock</option>
                    <option value="in" {{ $stock === 'in' ? 'selected' : '' }}>In Stock</option>
                    <option value="low" {{ $stock === 'low' ? 'selected' : '' }}>At / Below Par</option>
                    <option value="out" {{ $stock === 'out' ? 'selected' : '' }}>Out of Stock</option>
                    <option value="expiring" {{ $stock === 'expiring' ? 'selected' : '' }}>Expiring in 90 Days</option>
                    <option value="expired" {{ $stock === 'expired' ? 'selected' : '' }}>Has Expired Stock</option>
                </select>
                <select name="sort" class="px-3 py-2.5 border border-gray-300 rounded text-base">
                    <option value="recent" {{ $sort === 'recent' ? 'selected' : '' }}>Recently Updated</option>
                    <option value="fefo" {{ $sort === 'fefo' ? 'selected' : '' }}>FEFO</option>
                    <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Medicine Name</option>
                    <option value="low" {{ $sort === 'low' ? 'selected' : '' }}>Stock: Low to High</option>
                    <option value="high" {{ $sort === 'high' ? 'selected' : '' }}>Stock: High to Low</option>
                </select>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded text-sm min-h-11">Filter</button>
            </form>
            <p id="inventorySearchResultCount" class="mt-3 text-sm text-gray-500">Showing {{ $inventory->total() }} medicine(s).</p>
            <script>window.inventoryMedicineNames = @json($inventoryMedicineNames);</script>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[950px]">
                <thead>
                    <tr class="bg-gray-50 text-left text-sm text-gray-600">
                        <th class="px-4 py-3">Medicine</th>
                        <th class="px-4 py-3">Dosage</th>
                        <th class="px-4 py-3">Total Available</th>
                        <th class="px-4 py-3">Par Level</th>
                        <th class="px-4 py-3">Nearest Valid Expiry</th>
                        <th class="px-4 py-3">Current Price</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    @forelse($inventory as $item)
                        @php
                            $available = (int) $item->available_stock;
                            $physical = (int) $item->physical_stock;
                            $isLow = $available > 0 && $item->par_level > 0 && $available <= $item->par_level;
                            $isOut = $available === 0;
                            $nearestExpiry = $item->nearest_valid_expiry;
                            $daysToExpiry = $nearestExpiry ? (int) now()->startOfDay()->diffInDays($nearestExpiry->startOfDay(), false) : null;
                        @endphp
                        <tr class="border-t border-gray-200 {{ $isOut ? 'bg-red-50' : ($isLow ? 'bg-yellow-50' : '') }}">
                            <td class="px-4 py-3" data-medicine-name>
                                <p class="font-semibold text-gray-800">{{ $item->medicine->medicine_name }}</p>
                                @if($item->medicine->brand_name)<p class="text-sm text-gray-500">{{ $item->medicine->brand_name }}</p>@endif
                                <div class="mt-1 flex gap-2">
                                    @if($item->medicine->cold_chain_required)<span class="text-xs text-blue-600" title="Cold-chain required"><i class="fas fa-snowflake mr-1"></i>Cold chain</span>@endif
                                    @if($item->is_controlled)<span class="text-xs text-red-600"><i class="fas fa-shield-halved mr-1"></i>Controlled</span>@endif
                                </div>
                            </td>
                            <td class="px-4 py-3" data-medicine-dosage>{{ $item->medicine->dosage ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <p class="text-lg font-bold {{ $isOut ? 'text-red-700' : 'text-gray-800' }}">{{ $available }}</p>
                                @if($physical !== $available)
                                    <p class="text-xs text-red-600">Physical: {{ $physical }} (includes expired)</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $item->par_level ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if($nearestExpiry)
                                    <p>{{ $nearestExpiry->format('M d, Y') }}</p>
                                    @if($daysToExpiry !== null && $daysToExpiry <= 90)<p class="text-xs {{ $daysToExpiry <= 30 ? 'text-orange-700 font-semibold' : 'text-gray-500' }}">{{ $daysToExpiry }} day(s)</p>@endif
                                @else
                                    <span class="text-gray-400">No dated available batch</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">₱{{ number_format((float) $item->representative_price, 2) }}</td>
                            <td class="px-4 py-3">
                                @if($isOut)
                                    <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700">Out of Stock</span>
                                @elseif($isLow)
                                    <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-700">At / Below Par</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">In Stock</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('pharmacy.inventory.batches', ['inventory_item_id' => $item->id]) }}" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-800 px-3 py-1 rounded text-xs">View Batches</a>
                                    <a href="{{ route('pharmacy.receiving.create', ['inventory_item_id' => $item->id]) }}" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs">Add Stock</a>
                                    <a href="{{ route('pharmacy.inventory.edit', $item->id) }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1 rounded text-xs">Edit Medicine</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No medicines found. Add a medicine master to get started.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inventory->hasPages())
            <div class="p-6 border-t border-gray-200">{{ $inventory->links() }}</div>
        @endif
    </div>
</div>
@endsection
