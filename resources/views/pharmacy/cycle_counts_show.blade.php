@extends('layouts.app')

@section('title', 'Cycle Count: ' . $count->name)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📋 {{ $count->name }}</h1>
        <x-back-button :href="route('pharmacy.cycle-counts.index')" label="Back to Cycle Counts" />
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-sm text-gray-500">Scheduled</p>
                <p class="font-medium">{{ \Carbon\Carbon::parse($count->scheduled_at)->format('M d, Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Status</p>
                <p class="font-medium">
                    @if($count->completed_at)
                        <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">Completed ({{ \Carbon\Carbon::parse($count->completed_at)->format('M d, Y H:i') }})</span>
                    @else
                        <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-700">Pending</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Items</p>
                <p class="font-medium">{{ $count->items ? $count->items->count() : 0 }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Discrepancies</p>
                <p class="font-medium">{{ $count->items ? $count->items->where('discrepancy', '!=', 0)->count() : 0 }}</p>
            </div>
        </div>
        @if($count->notes)
            <p class="text-sm text-gray-600 mt-3"><strong>Notes:</strong> {{ $count->notes }}</p>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6 pb-0 flex flex-col md:flex-row md:items-center md:justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Count Items</h3>
            @if(!$count->completed_at)
                <p class="text-sm text-gray-500">Enter actual quantities, then complete the count.</p>
            @endif
        </div>

        @if($count->completed_at)
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-left text-gray-600">
                        <th class="px-4 py-3">Medicine</th>
                        <th class="px-4 py-3">Expected</th>
                        <th class="px-4 py-3">Counted</th>
                        <th class="px-4 py-3">Discrepancy</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($count->items as $item)
                        <tr class="border-t border-gray-200">
                            <td class="px-4 py-3 font-medium">{{ $item->inventoryItem->medicine->medicine_name }}</td>
                            <td class="px-4 py-3">{{ $item->expected_quantity }}</td>
                            <td class="px-4 py-3">{{ $item->counted_quantity }}</td>
                            <td class="px-4 py-3">
                                @if($item->discrepancy != 0)
                                    <span class="font-semibold {{ $item->discrepancy > 0 ? 'text-green-600' : 'text-red-600' }}">{{ $item->discrepancy > 0 ? '+' : '' }}{{ $item->discrepancy }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($item->discrepancy == 0)
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">Match</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700">Variance</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No items in this count.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <form method="POST" action="{{ route('pharmacy.cycle-counts.complete', $count->id) }}">
                @csrf
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-600">
                            <th class="px-4 py-3">Medicine</th>
                            <th class="px-4 py-3">Expected</th>
                            <th class="px-4 py-3">Counted</th>
                            <th class="px-4 py-3">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($count->items as $item)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 font-medium">{{ $item->inventoryItem->medicine->medicine_name }}</td>
                                <td class="px-4 py-3">{{ $item->expected_quantity }}</td>
                                <td class="px-4 py-3">
                                    <input type="number" name="counted[{{ $item->id }}]" value="{{ $item->counted_quantity ?? $item->expected_quantity }}" min="0" class="w-24 px-2 py-1 border border-gray-300 rounded">
                                </td>
                                <td class="px-4 py-3" id="var-{{ $item->id }}">—</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No items in this count.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Completion Notes</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded px-3 py-2"></textarea>
                    <button type="submit" class="mt-3 bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded">
                        <i class="fas fa-check mr-2"></i>Complete Cycle Count
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
