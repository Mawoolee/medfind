{{-- resources/views/pharmacy/dashboard.blade.php --}}

@extends('layouts.app')

@section('title', 'Pharmacy Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Pharmacy Dashboard</h1>
        <span class="text-sm text-gray-500">{{ $pharmacy->pharmacy_name ?? 'No pharmacy assigned' }}</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
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
                    <p class="text-gray-500 text-sm">Messages</p>
                    <p class="text-2xl font-bold text-purple-600">{{ $messageCount ?? 0 }}</p>
                </div>
                <i class="fas fa-envelope text-4xl text-purple-200"></i>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Manage Inventory -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Manage Inventory</h2>
            <p class="text-gray-600 mb-4">Update your medicine stock levels in real-time.</p>
            <a href="{{ route('pharmacy.inventory') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition duration-200">
                <i class="fas fa-edit mr-2"></i>Update Stock
            </a>
        </div>

        <!-- Messages -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Messages</h2>
            <p class="text-gray-600 mb-4">View and respond to customer inquiries.</p>
            <a href="{{ route('pharmacy.messages') }}" class="inline-block bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg transition duration-200">
                <i class="fas fa-comments mr-2"></i>View Messages
                @if($unreadCount ?? 0 > 0)
                    <span class="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-1">{{ $unreadCount }}</span>
                @endif
            </a>
        </div>
    </div>
</div>
@endsection