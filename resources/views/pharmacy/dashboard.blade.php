{{-- resources/views/pharmacy/dashboard.blade.php --}}

@extends('layouts.app')

@section('title', 'Pharmacy Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Pharmacy Dashboard</h1>
        <span class="text-sm text-gray-500">{{ $pharmacy->pharmacy_name ?? 'No pharmacy assigned' }}</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Medicines</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $inventoryCount ?? 0 }}</p>
                </div>
                <i class="fas fa-capsules text-4xl text-blue-200"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">In Stock</p>
                    <p class="text-2xl font-bold text-green-600">{{ $inStockCount ?? 0 }}</p>
                </div>
                <i class="fas fa-check-circle text-4xl text-green-200"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Messages</p>
                    <p class="text-2xl font-bold text-purple-600">{{ $messageCount ?? 0 }}</p>
                </div>
                <i class="fas fa-envelope text-4xl text-purple-200"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Unread</p>
                    <p class="text-2xl font-bold text-red-600">{{ $unreadCount ?? 0 }}</p>
                </div>
                <i class="fas fa-envelope-open text-4xl text-red-200"></i>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Manage Inventory -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">📦 Manage Inventory</h2>
            <p class="text-gray-600 mb-4">Update your medicine stock levels and prices in real-time.</p>
            <a href="{{ route('pharmacy.inventory') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition duration-200">
                <i class="fas fa-edit mr-2"></i>Update Stock
            </a>
        </div>

        <!-- Messages -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">💬 Messages</h2>
            <p class="text-gray-600 mb-4">View and respond to customer inquiries and prescription verifications.</p>
            <a href="{{ route('pharmacy.messages') }}" class="inline-block bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg transition duration-200">
                <i class="fas fa-comments mr-2"></i>View Messages
                @if($unreadCount > 0)
                    <span class="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-1">{{ $unreadCount }}</span>
                @endif
            </a>
        </div>
    </div>

    <!-- Quick Stats Table -->
    <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Inventory Overview</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left text-gray-600">Medicine</th>
                        <th class="px-4 py-2 text-left text-gray-600">Dosage</th>
                        <th class="px-4 py-2 text-left text-gray-600">Stock</th>
                        <th class="px-4 py-2 text-left text-gray-600">Price</th>
                        <th class="px-4 py-2 text-left text-gray-600">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $inventoryItems = App\Models\InventoryItem::with('medicine')
                            ->where('pharmacy_id', $pharmacy->id)
                            ->limit(5)
                            ->get();
                    @endphp
                    @forelse($inventoryItems as $item)
                        <tr class="border-t border-gray-200">
                            <td class="px-4 py-2">{{ $item->medicine->medicine_name }}</td>
                            <td class="px-4 py-2">{{ $item->medicine->dosage }}</td>
                            <td class="px-4 py-2">{{ $item->stockQuantity }}</td>
                            <td class="px-4 py-2">₱{{ number_format($item->price, 2) }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs {{ $item->stockQuantity > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $item->stockQuantity > 0 ? 'In Stock' : 'Out of Stock' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-gray-500">No inventory items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($inventoryItems->count() > 0)
            <div class="mt-4 text-right">
                <a href="{{ route('pharmacy.inventory') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                    View All Inventory →
                </a>
            </div>
        @endif
    </div>
</div>
@endsection