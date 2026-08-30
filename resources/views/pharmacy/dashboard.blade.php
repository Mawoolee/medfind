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
            <i class="fas fa-clock text-amber-500 mt-1"></i>
            <div>
                <p class="font-bold text-amber-800 text-sm">Account Pending Approval</p>
                <p class="text-amber-700 text-xs mt-1">Your pharmacy is under review. <a href="{{ route('pharmacy.requirements') }}" class="underline font-semibold">View or upload requirements</a>.</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        @foreach([
            ['label' => 'Total Medicines', 'value' => $inventoryCount, 'color' => 'blue', 'icon' => 'fa-capsules'],
            ['label' => 'In Stock', 'value' => $inStockCount, 'color' => 'green', 'icon' => 'fa-check-circle'],
            ['label' => 'Total Messages', 'value' => $messageCount, 'color' => 'purple', 'icon' => 'fa-envelope'],
            ['label' => 'Unread', 'value' => $unreadCount, 'color' => 'red', 'icon' => 'fa-envelope-open'],
            ['label' => 'Searches Today', 'value' => $searchCountToday, 'color' => 'cyan', 'icon' => 'fa-magnifying-glass-chart'],
            ['label' => 'Total Searches', 'value' => $searchCountTotal, 'color' => 'indigo', 'icon' => 'fa-chart-line'],
        ] as $stat)
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div><p class="text-gray-500 text-sm">{{ $stat['label'] }}</p><p class="text-2xl font-bold text-{{ $stat['color'] }}-600">{{ $stat['value'] }}</p></div>
                    <i class="fas {{ $stat['icon'] }} text-4xl text-{{ $stat['color'] }}-200"></i>
                </div>
                @if($stat['label'] === 'Searches Today')<p class="text-xs text-gray-400 mt-1">{{ $searchCountWeek }} this week</p>@endif
            </div>
        @endforeach
    </div>

    @if($lowStockCount > 0 || $expiredCount > 0 || $expiringItems->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            @if($lowStockCount > 0)
                <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-r-lg p-4">
                    <div class="flex items-center justify-between mb-3"><h2 class="font-semibold text-yellow-800"><i class="fas fa-triangle-exclamation mr-2"></i>Low Stock Alerts</h2><span class="bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ $lowStockCount }}</span></div>
                    <ul class="space-y-1 text-sm">
                        @foreach($lowStockItems as $item)
                            <li class="flex justify-between gap-3"><span class="text-yellow-900">{{ $item->medicine->medicine_name }}</span><span class="text-yellow-700 font-medium">Stock: {{ $item->available_stock }} / Par: {{ $item->par_level }}</span></li>
                        @endforeach
                    </ul>
                    <a href="{{ route('pharmacy.inventory', ['stock' => 'low']) }}" class="inline-block mt-3 text-sm text-yellow-700 hover:text-yellow-900">View all low stock</a>
                </div>
            @endif

            @if($expiredCount > 0 || $expiringItems->isNotEmpty())
                <div class="bg-orange-50 border-l-4 border-orange-500 rounded-r-lg p-4">
                    <div class="flex items-center justify-between mb-3"><h2 class="font-semibold text-orange-800"><i class="fas fa-hourglass-half mr-2"></i>Expiry / FEFO Alerts</h2>@if($expiredCount > 0)<span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ $expiredCount }} medicine(s) with expired stock</span>@endif</div>
                    <ul class="space-y-1 text-sm">
                        @foreach($expiringItems as $item)
                            <li class="flex justify-between gap-3"><span class="text-orange-900">{{ $item->medicine->medicine_name }}</span><span class="text-orange-700 font-medium">{{ $item->nearest_valid_expiry?->format('M d, Y') }}</span></li>
                        @endforeach
                    </ul>
                    <a href="{{ route('pharmacy.inventory', ['sort' => 'fefo']) }}" class="inline-block mt-3 text-sm text-orange-700 hover:text-orange-900">View inventory by FEFO</a>
                </div>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col"><h2 class="text-lg font-semibold text-gray-800 mb-2">Manage Inventory</h2><p class="text-gray-600 mb-4 flex-1">View one aggregate row per medicine and monitor par levels.</p><a href="{{ route('pharmacy.inventory') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded w-fit"><i class="fas fa-boxes-stacked mr-2"></i>Manage Inventory</a></div>
        <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col"><h2 class="text-lg font-semibold text-gray-800 mb-2">Add New Medicine</h2><p class="text-gray-600 mb-4 flex-1">Create product identity without batch or stock details.</p><a href="{{ route('pharmacy.inventory.create') }}" class="bg-teal-600 hover:bg-teal-700 text-white px-5 py-2 rounded w-fit"><i class="fas fa-capsules mr-2"></i>Add Medicine</a></div>
        <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col"><h2 class="text-lg font-semibold text-gray-800 mb-2">Add Stock / Receive Delivery</h2><p class="text-gray-600 mb-4 flex-1">Record every delivery as a separate traceable batch.</p><a href="{{ route('pharmacy.receiving.create') }}" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded w-fit"><i class="fas fa-truck-ramp-box mr-2"></i>Receive Delivery</a></div>
        <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col"><h2 class="text-lg font-semibold text-gray-800 mb-2">View Stock Batches</h2><p class="text-gray-600 mb-4 flex-1">Review batches, lots, suppliers, expiry dates, and remaining stock.</p><a href="{{ route('pharmacy.inventory.batches') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded w-fit"><i class="fas fa-layer-group mr-2"></i>View Batches</a></div>
        <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col"><h2 class="text-lg font-semibold text-gray-800 mb-2">Messages</h2><p class="text-gray-600 mb-4 flex-1">Respond to customer inquiries and prescription verification.</p><a href="{{ route('pharmacy.messages') }}" class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded w-fit"><i class="fas fa-comments mr-2"></i>Messages @if($unreadCount > 0)<span class="ml-1 bg-red-500 text-xs rounded-full px-2 py-1">{{ $unreadCount }}</span>@endif</a></div>
        <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col"><h2 class="text-lg font-semibold text-gray-800 mb-2">Audit Log</h2><p class="text-gray-600 mb-4 flex-1">Review correlated stock quantity changes.</p><a href="{{ route('pharmacy.audit-log') }}" class="bg-gray-700 hover:bg-gray-800 text-white px-5 py-2 rounded w-fit"><i class="fas fa-history mr-2"></i>Audit Log</a></div>
        <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col"><h2 class="text-lg font-semibold text-gray-800 mb-2">Controlled Substances</h2><p class="text-gray-600 mb-4 flex-1">Record dispensing, wastage, and transfers.</p><a href="{{ route('pharmacy.controlled-substances.create') }}" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 rounded w-fit"><i class="fas fa-shield-halved mr-2"></i>Log Entry</a></div>
    </div>

    <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Inventory Overview</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="bg-gray-50 text-left text-gray-600"><th class="px-4 py-2">Medicine</th><th class="px-4 py-2">Dosage</th><th class="px-4 py-2">Available Stock</th><th class="px-4 py-2">Price</th><th class="px-4 py-2">Status</th></tr></thead>
                <tbody>
                    @forelse($recentInventory as $item)
                        <tr class="border-t border-gray-200"><td class="px-4 py-2">{{ $item->medicine->medicine_name }}</td><td class="px-4 py-2">{{ $item->medicine->dosage }}</td><td class="px-4 py-2">{{ $item->available_stock }}</td><td class="px-4 py-2">₱{{ number_format((float) $item->representative_price, 2) }}</td><td class="px-4 py-2"><span class="px-2 py-1 rounded text-xs {{ $item->available_stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $item->available_stock > 0 ? 'In Stock' : 'Out of Stock' }}</span></td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No inventory items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recentInventory->isNotEmpty())<div class="mt-4 text-right"><a href="{{ route('pharmacy.inventory') }}" class="text-blue-600 hover:text-blue-800 text-sm">View All Inventory</a></div>@endif
    </div>

    <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Most Searched Medicines</h2>
        @if($topSearchQueries->isNotEmpty())
            <div class="space-y-3">
                @foreach($topSearchQueries as $index => $search)
                    <div class="flex items-center gap-4"><span class="w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold bg-indigo-50 text-indigo-700">{{ $index + 1 }}</span><span class="flex-1 text-gray-800 font-medium">{{ $search->query }}</span><span class="text-sm text-gray-500">{{ $search->total }} search(es)</span></div>
                @endforeach
            </div>
        @else
            <p class="text-gray-500 text-sm">No search data yet.</p>
        @endif
    </div>
</div>
@endsection
