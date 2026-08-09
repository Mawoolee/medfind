@extends('layouts.app')

@section('title', 'Inventory Audit Log')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">📋 Inventory Audit Log</h1>
            <p class="text-sm text-gray-500 mt-1">Every stock change recorded — who changed what, when, and by how much.</p>
        </div>
        <a href="{{ route('pharmacy.inventory') }}" class="text-[#9400D3] hover:text-[#7a00b0] text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Inventory
        </a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 mb-6">
        <form method="GET" action="{{ route('pharmacy.audit-log') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Medicine</label>
                <input type="text" name="q" value="{{ $q }}" placeholder="Search medicine..."
                    class="border border-gray-300 rounded px-3 py-1.5 text-sm w-48 focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}"
                    class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}"
                    class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Change Type</label>
                <select name="change" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
                    <option value="">All changes</option>
                    <option value="increase" {{ $change === 'increase' ? 'selected' : '' }}>Increases only</option>
                    <option value="decrease" {{ $change === 'decrease' ? 'selected' : '' }}>Decreases only</option>
                </select>
            </div>
            <button type="submit" class="bg-[#9400D3] text-white px-4 py-1.5 rounded text-sm hover:bg-[#7a00b0] transition">
                <i class="fas fa-filter mr-1"></i>Filter
            </button>
            @if($q || $from || $to || $change)
                <a href="{{ route('pharmacy.audit-log') }}" class="text-sm text-gray-500 hover:text-gray-700 py-1.5">
                    <i class="fas fa-times mr-1"></i>Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-[#9400D3]/10 flex items-center justify-center">
                <i class="fas fa-history text-[#9400D3]"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Total Entries</p>
                <p class="text-xl font-bold text-gray-800">{{ number_format($totalCount) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                <i class="fas fa-arrow-up text-green-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Stock Increases</p>
                <p class="text-xl font-bold text-green-700">{{ number_format($increaseCount) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <i class="fas fa-arrow-down text-red-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Stock Decreases</p>
                <p class="text-xl font-bold text-red-700">{{ number_format($decreaseCount) }}</p>
            </div>
        </div>
    </div>

    {{-- Audit Table --}}
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Date & Time</th>
                        <th class="px-4 py-3">Medicine</th>
                        <th class="px-4 py-3">Before</th>
                        <th class="px-4 py-3">After</th>
                        <th class="px-4 py-3">Change</th>
                        <th class="px-4 py-3">Changed By</th>
                        <th class="px-4 py-3">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($audits as $audit)
                        @php
                            $diff = $audit->after_quantity - $audit->before_quantity;
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                {{ $audit->created_at->format('M d, Y') }}<br>
                                <span class="text-xs text-gray-400">{{ $audit->created_at->format('H:i:s') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 text-sm">{{ $audit->inventoryItem?->medicine?->medicine_name ?? '—' }}</p>
                                <p class="text-xs text-gray-400">{{ $audit->inventoryItem?->medicine?->dosage ?? '' }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ $audit->before_quantity }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ $audit->after_quantity }}</td>
                            <td class="px-4 py-3">
                                @if($diff > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        <i class="fas fa-arrow-up text-[10px]"></i>+{{ $diff }}
                                    </span>
                                @elseif($diff < 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                        <i class="fas fa-arrow-down text-[10px]"></i>{{ $diff }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">No change</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $audit->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $audit->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                <i class="fas fa-box-open text-3xl mb-2 block text-gray-300"></i>
                                No audit entries found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($audits->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $audits->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
