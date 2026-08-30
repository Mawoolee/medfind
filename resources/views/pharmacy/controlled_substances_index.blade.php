@extends('layouts.app')

@section('title', 'Controlled Substances Logbook')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">🛡️ Controlled Substances Logbook</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('pharmacy.controlled-substances.create') }}"
               class="bg-[#9400D3] text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-[#7a00b0] transition">
                <i class="fas fa-plus mr-2"></i>Log Entry
            </a>
            <x-back-button :href="route('pharmacy.dashboard')" label="Back to Pharmacy Dashboard" />
        </div>
    </div>

    <!-- Controlled items snapshot -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Controlled Substances in Stock</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-left text-gray-600">
                        <th class="px-4 py-3">Medicine</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3">Batch</th>
                        <th class="px-4 py-3">Expiry</th>
                        <th class="px-4 py-3">Secure Storage</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($controlledItems as $item)
                        <tr class="border-t border-gray-200">
                            <td class="px-4 py-3 font-medium">{{ $item->medicine->medicine_name }}</td>
                            <td class="px-4 py-3">{{ $item->stockQuantity }}</td>
                            <td class="px-4 py-3">{{ $item->batch_number ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $item->expiry_date ? \Carbon\Carbon::parse($item->expiry_date)->format('M d, Y') : '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">Requires secure storage</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No controlled substances currently in stock.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Logbook -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6 flex flex-col md:flex-row md:items-center md:justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Logbook Entries</h3>
            <form method="GET" action="{{ route('pharmacy.controlled-substances.index') }}" class="mt-2 md:mt-0">
                <select name="action" onchange="this.form.submit()" class="border border-gray-300 rounded px-3 py-1 text-sm">
                    <option value="">All actions</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" {{ $action === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-gray-600">
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Medicine</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Quantity</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-t border-gray-200">
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($log->logged_at)->format('M d, Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $log->inventoryItem?->medicine?->medicine_name ?? 'N/A' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs {{ $log->action === 'receive' ? 'bg-green-100 text-green-700' : ($log->action === 'dispense' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700') }}">
                                {{ ucfirst($log->action) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $log->quantity }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? 'N/A' }}</td>
                        <td class="px-4 py-3">{{ $log->notes ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No logbook entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
