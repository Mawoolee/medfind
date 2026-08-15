{{-- resources/views/pharmacy/dashboard.blade.php --}}

@extends('layouts.app')

@section('title', 'Pharmacy Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
 <div class="flex items-center justify-between mb-6">
 <h1 class="text-2xl font-bold text-gray-800">Pharmacy Dashboard</h1>
 <span class="text-sm text-gray-500">{{ $pharmacy->pharmacy_name ?? 'No pharmacy assigned' }}</span>
 </div>

@if($pharmacy->status === 'pending')
<div class="bg-amber-50 border border-amber-300 rounded-xl px-5 py-4 mb-6 flex items-start gap-3">
 <span class="text-amber-500 text-xl mt-0.5">&#x23F3;</span>
 <div>
 <p class="font-bold text-amber-800 text-sm">Account Pending Approval</p>
 <p class="text-amber-700 text-xs mt-0.5">Your pharmacy is currently under review. You have limited access until approved by an admin. <a href="{{ route('pharmacy.requirements') }}" class="underline font-semibold">View / upload requirements &rarr;</a></p>
 </div>
</div>
@endif

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
 <div class="bg-white rounded-lg shadow-lg p-6">
 <div class="flex items-center justify-between">
 <div>
 <p class="text-gray-500 text-sm">Searches Today</p>
 <p class="text-2xl font-bold text-cyan-600">{{ $searchCountToday ?? 0 }}</p>
 <p class="text-xs text-gray-400 mt-1">{{ $searchCountWeek ?? 0 }} this week</p>
 </div>
 <i class="fas fa-magnifying-glass-chart text-4xl text-cyan-200"></i>
 </div>
 </div>
 <div class="bg-white rounded-lg shadow-lg p-6">
 <div class="flex items-center justify-between">
 <div>
 <p class="text-gray-500 text-sm">Total Searches</p>
 <p class="text-2xl font-bold text-indigo-600">{{ $searchCountTotal ?? 0 }}</p>
 </div>
 <i class="fas fa-chart-line text-4xl text-indigo-200"></i>
 </div>
 </div>
 </div>

 @if($lowStockCount > 0 || $expiredCount > 0)
 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
 @if($lowStockCount > 0)
 <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-r-lg p-4">
 <div class="flex items-center justify-between mb-3">
 <h3 class="font-semibold text-yellow-800">
 <i class="fas fa-triangle-exclamation mr-2"></i>Low Stock Alerts
 </h3>
 <span class="bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ $lowStockCount }}</span>
 </div>
 <ul class="space-y-1 text-sm">
 @foreach($lowStockItems as $ls)
 <li class="flex justify-between">
 <span class="text-yellow-900">{{ $ls->medicine->medicine_name }}</span>
 <span class="text-yellow-700 font-medium">Stock: {{ $ls->stockQuantity }} @if($ls->par_level) / Par: {{ $ls->par_level }} @endif</span>
 </li>
 @endforeach
 </ul>
 <a href="{{ route('pharmacy.inventory', ['stock' => 'low']) }}" class="inline-block mt-3 text-sm text-yellow-700 hover:text-yellow-900">View all low stock →</a>
 </div>
 @endif

 @if($expiredCount > 0 || $expiringItems->count() > 0)
 <div class="bg-orange-50 border-l-4 border-orange-500 rounded-r-lg p-4">
 <div class="flex items-center justify-between mb-3">
 <h3 class="font-semibold text-orange-800">
 <i class="fas fa-hourglass-half mr-2"></i>Expiring / FEFO Alerts
 </h3>
 @if($expiredCount > 0)
 <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ $expiredCount }} Expired</span>
 @endif
 </div>
 <ul class="space-y-1 text-sm">
 @foreach($expiringItems as $ex)
 <li class="flex justify-between">
 <span class="text-orange-900">{{ $ex->medicine->medicine_name }}</span>
 <span class="text-orange-700 font-medium">{{ $ex->expiry_date->format('M d, Y') }} ({{ $ex->days_until_expiry }}d)</span>
 </li>
 @endforeach
 </ul>
 <a href="{{ route('pharmacy.inventory', ['sort' => 'fefo']) }}" class="inline-block mt-3 text-sm text-orange-700 hover:text-orange-900">View all by FEFO →</a>
 </div>
 @endif
 </div>
 @endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
 <!-- Manage Inventory -->
 <div class="bg-white rounded-lg shadow-lg p-6">
 <h2 class="text-xl font-semibold text-gray-800 mb-4"> Manage Inventory</h2>
 <p class="text-gray-600 mb-4">Update your medicine stock levels and prices in real-time.</p>
 <a href="{{ route('pharmacy.inventory') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition duration-200">
 <i class="fas fa-edit mr-2"></i>Update Stock
 </a>
 </div>

 <!-- Add New Medicine -->
 <div class="bg-white rounded-lg shadow-lg p-6">
 <h2 class="text-xl font-semibold text-gray-800 mb-4"> Add New Medicine</h2>
 <p class="text-gray-600 mb-4">Add a new medicine to your inventory.</p>
 <a href="{{ route('pharmacy.inventory.create') }}" class="inline-block bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition duration-200">
 <i class="fas fa-plus mr-2"></i>Add Medicine
 </a>
 </div>

 <!-- Messages -->
 <div class="bg-white rounded-lg shadow-lg p-6">
 <h2 class="text-xl font-semibold text-gray-800 mb-4"> Messages</h2>
 <p class="text-gray-600 mb-4">View and respond to customer inquiries and prescription verifications.</p>
 <a href="{{ route('pharmacy.messages') }}" class="inline-block bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg transition duration-200">
 <i class="fas fa-comments mr-2"></i>View Messages
 @if($unreadCount > 0)
 <span class="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-1">{{ $unreadCount }}</span>
 @endif
 </a>
 </div>
 <!-- Audit Log -->
 <div class="bg-white rounded-lg shadow-lg p-6">
 <h2 class="text-xl font-semibold text-gray-800 mb-4"> Audit Log</h2>
 <p class="text-gray-600 mb-4">Review every stock change — who changed what and when.</p>
 <a href="{{ route('pharmacy.audit-log') }}" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg transition duration-200">
 <i class="fas fa-history mr-2"></i>View Audit Log
 </a>
 </div>
 <!-- Controlled Substances -->
 <div class="bg-white rounded-lg shadow-lg p-6">
 <h2 class="text-xl font-semibold text-gray-800 mb-4"> Controlled Substances</h2>
 <p class="text-gray-600 mb-4">Logbook for dispensing, wastage, and transfer entries.</p>
 <a href="{{ route('pharmacy.controlled-substances.create') }}" class="inline-block bg-rose-600 hover:bg-rose-700 text-white px-6 py-3 rounded-lg transition duration-200">
 <i class="fas fa-shield-halved mr-2"></i>Log Entry
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
<!-- Top Searched Medicines (Search interest) -->
 <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
 <h3 class="text-lg font-semibold text-gray-800 mb-4"> Most Searched Medicines</h3>
 @if(isset($topSearchQueries) && $topSearchQueries->count() > 0)
 <div class="space-y-3">
 @foreach($topSearchQueries as $idx => $sq)
 <div class="flex items-center gap-4">
 <span class="w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold
 {{ $idx === 0 ? 'bg-amber-100 text-amber-700' : ($idx === 1 ? 'bg-gray-200 text-gray-700' : ($idx === 2 ? 'bg-orange-100 text-orange-700' : 'bg-cyan-50 text-cyan-700')) }}">
 {{ $idx + 1 }}
 </span>
 <span class="flex-1 text-gray-800 font-medium">{{ $sq->query }}</span>
 <span class="text-sm text-gray-500">{{ $sq->total }} {{ $sq->total === 1 ? 'search' : 'searches' }}</span>
 <div class="w-32 h-2 bg-gray-100 rounded-full overflow-hidden">
 <div class="h-full bg-[#9400D3] rounded-full" style="width: {{ $topSearchQueries->max('total') > 0 ? ($sq->total / $topSearchQueries->max('total') * 100) : 0 }}%"></div>
 </div>
 </div>
 @endforeach
 </div>
 @else
 <p class="text-gray-500 text-sm">No search data yet. When consumers search for medicines and your pharmacy matches, it will appear here.</p>
 @endif
 </div>
</div>
@endsection
