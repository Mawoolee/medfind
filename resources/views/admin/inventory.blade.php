@extends('layouts.app')

@section('title', 'System Inventory Overview')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">📦 System Inventory Overview</h1>
            <p class="text-sm text-gray-500 mt-1">Real-time stock levels across all partner pharmacies.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="text-[#9400D3] hover:text-[#7a00b0] text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Total SKUs</p>
            <p class="text-2xl font-bold text-[#9400D3]">{{ number_format($totalSkus) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">In Stock</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format($inStockCount) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Out of Stock</p>
            <p class="text-2xl font-bold text-red-600">{{ number_format($outOfStockCount) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Low Stock Alerts</p>
            <p class="text-2xl font-bold text-yellow-600">{{ number_format($lowStockCount) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 mb-6">
        <form method="GET" action="{{ route('admin.inventory') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Medicine</label>
                <input type="text" name="q" value="{{ $q }}" placeholder="Search medicine..."
                    class="border border-gray-300 rounded px-3 py-1.5 text-sm w-48 focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Pharmacy</label>
                <select name="pharmacy_id" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
                    <option value="">All pharmacies</option>
                    @foreach($pharmacies as $p)
                        <option value="{{ $p->id }}" {{ $pharmacyId == $p->id ? 'selected' : '' }}>{{ $p->pharmacy_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Stock Status</label>
                <select name="stock" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
                    <option value="">All</option>
                    <option value="in" {{ $stock === 'in' ? 'selected' : '' }}>In Stock</option>
                    <option value="out" {{ $stock === 'out' ? 'selected' : '' }}>Out of Stock</option>
                    <option value="low" {{ $stock === 'low' ? 'selected' : '' }}>Low Stock</option>
                    <option value="expiring" {{ $stock === 'expiring' ? 'selected' : '' }}>Expiring (90 days)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Category</label>
                <select name="category" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#9400D3] text-white px-4 py-1.5 rounded text-sm hover:bg-[#7a00b0] transition">
                <i class="fas fa-filter mr-1"></i>Filter
            </button>
            @if($q || $pharmacyId || $stock || $category)
                <a href="{{ route('admin.inventory') }}" class="text-sm text-gray-500 hover:text-gray-700 py-1.5">
                    <i class="fas fa-times mr-1"></i>Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Per-Pharmacy Stock Summary --}}
    @if(!$pharmacyId && !$q && !$stock && !$category)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        @foreach($pharmacySummaries as $summary)
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-semibold text-gray-800 text-sm">{{ $summary['name'] }}</h3>
                <span class="text-xs {{ $summary['out'] > 0 ? 'text-red-600' : 'text-green-600' }} font-medium">
                    {{ $summary['out'] > 0 ? $summary['out'].' out of stock' : 'Fully stocked' }}
                </span>
            </div>
            <div class="flex gap-3 text-xs text-gray-500">
                <span><i class="fas fa-box text-[#9400D3] mr-1"></i>{{ $summary['total'] }} SKUs</span>
                <span><i class="fas fa-check-circle text-green-500 mr-1"></i>{{ $summary['in'] }} in stock</span>
                @if($summary['low'] > 0)
                    <span><i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i>{{ $summary['low'] }} low</span>
                @endif
            </div>
            <div class="mt-2 w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                @php $pct = $summary['total'] > 0 ? ($summary['in'] / $summary['total'] * 100) : 0; @endphp
                <div class="h-full rounded-full {{ $pct > 70 ? 'bg-green-500' : ($pct > 40 ? 'bg-yellow-400' : 'bg-red-500') }}" style="width:{{ $pct }}%"></div>
            </div>
            <a href="{{ route('admin.inventory', ['pharmacy_id' => $summary['id']]) }}" class="inline-block mt-2 text-xs text-[#9400D3] hover:underline">View inventory →</a>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Inventory Table --}}
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <p class="text-sm text-gray-600">Showing <span class="font-semibold">{{ $items->total() }}</span> inventory records</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Pharmacy</th>
                        <th class="px-4 py-3">Medicine</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Expiry</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($items as $item)
                        @php
                            $available = (int) $item->available_stock;
                            $isOut = $available === 0;
                            $isLow = ! $isOut && $item->par_level > 0 && $available <= $item->par_level;
                            $nearestExpiry = $item->nearest_valid_expiry;
                            $daysToExpiry = $nearestExpiry
                                ? (int) now()->startOfDay()->diffInDays($nearestExpiry->startOfDay(), false)
                                : null;
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-800">{{ $item->pharmacy?->pharmacy_name }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-800">{{ $item->medicine?->medicine_name }}</p>
                                <p class="text-xs text-gray-400">{{ $item->medicine?->dosage }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $item->medicine?->category ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm font-mono font-medium {{ $isOut ? 'text-red-600' : ($isLow ? 'text-yellow-600' : 'text-gray-800') }}">
                                {{ $available }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">₱{{ number_format((float) $item->representative_price, 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($nearestExpiry)
                                    <span class="{{ $daysToExpiry !== null && $daysToExpiry <= 30 ? 'text-orange-600 font-semibold' : 'text-gray-600' }}">
                                        {{ $nearestExpiry->format('M d, Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($isOut)
                                    <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-700 font-medium">Out of Stock</span>
                                @elseif($isLow)
                                    <span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700 font-medium">Low Stock</span>
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700 font-medium">In Stock</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                <i class="fas fa-box-open text-3xl mb-2 block text-gray-300"></i>
                                No inventory records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $items->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
