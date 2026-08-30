@extends('layouts.app')

@section('title', 'Stock Batches')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Stock Batches</h1>
            <p class="text-sm text-gray-500 mt-1">
                @if($selectedInventory)
                    {{ $selectedInventory->medicine->medicine_name }} — {{ $selectedInventory->available_stock }} available across its valid batches
                @else
                    All batches are ordered by FEFO: nearest expiry first, no-expiry batches last.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('pharmacy.receiving.create', $selectedInventory ? ['inventory_item_id' => $selectedInventory->id] : []) }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm"><i class="fas fa-plus mr-1"></i>Add Stock</a>
            <a href="{{ route('pharmacy.inventory') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded text-sm">Manage Inventory</a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-5 border-b border-gray-200">
            <form method="GET" action="{{ route('pharmacy.inventory.batches') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <select name="inventory_item_id" class="border border-gray-300 rounded px-3 py-2 md:col-span-2">
                    <option value="">All medicines</option>
                    @foreach($inventory as $aggregate)
                        <option value="{{ $aggregate->id }}" {{ (string) $selectedInventoryId === (string) $aggregate->id ? 'selected' : '' }}>
                            {{ $aggregate->medicine->medicine_name }}@if($aggregate->medicine->dosage) ({{ $aggregate->medicine->dosage }})@endif
                        </option>
                    @endforeach
                </select>
                <input type="text" name="q" value="{{ $q }}" class="border border-gray-300 rounded px-3 py-2" placeholder="Batch, lot, supplier, medicine">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px]">
                <thead>
                    <tr class="bg-gray-50 text-left text-sm text-gray-600">
                        <th class="px-4 py-3">Medicine</th>
                        <th class="px-4 py-3">Batch / Lot</th>
                        <th class="px-4 py-3">Received</th>
                        <th class="px-4 py-3">Remaining</th>
                        <th class="px-4 py-3">Expiry</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Received Date</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        @php
                            $expired = $batch->expiry_date && $batch->expiry_date->isBefore(now()->startOfDay());
                            $depleted = $batch->current_quantity <= 0;
                        @endphp
                        <tr class="border-t border-gray-200 {{ $expired && !$depleted ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-800">{{ $batch->inventoryItem->medicine->medicine_name }}</p>
                                <p class="text-xs text-gray-500">{{ $batch->inventoryItem->medicine->brand_name }} {{ $batch->inventoryItem->medicine->dosage }}</p>
                            </td>
                            <td class="px-4 py-3"><p class="font-medium">{{ $batch->batch_number }}</p><p class="text-xs text-gray-500">Lot: {{ $batch->lot_number ?? '—' }}</p></td>
                            <td class="px-4 py-3">{{ $batch->quantity_received }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $batch->current_quantity }}</td>
                            <td class="px-4 py-3 {{ $expired ? 'text-red-700 font-semibold' : '' }}">{{ $batch->expiry_date?->format('M d, Y') ?? 'No expiry' }}</td>
                            <td class="px-4 py-3">₱{{ number_format((float) $batch->price, 2) }}</td>
                            <td class="px-4 py-3">{{ $batch->supplier_name ?? $batch->supplier?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $batch->received_date->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $batch->received_reference ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($depleted)
                                    <span class="px-2 py-1 rounded text-xs bg-gray-200 text-gray-700">Depleted</span>
                                @elseif($expired)
                                    <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700">Expired</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">Available</span>
                                @endif
                                @if($batch->cold_chain)<span class="block text-xs text-blue-600 mt-1"><i class="fas fa-snowflake mr-1"></i>Cold chain</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-10 text-center text-gray-500">No stock batches found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($batches->hasPages())
            <div class="p-5 border-t border-gray-200">{{ $batches->links() }}</div>
        @endif
    </div>
</div>
@endsection
