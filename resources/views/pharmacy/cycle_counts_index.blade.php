@extends('layouts.app')

@section('title', 'Cycle Counts')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📋 Cycle Counts</h1>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('pharmacy.cycle-counts.create') }}" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 min-h-11 rounded">
                <i class="fas fa-plus mr-2"></i>New Cycle Count
            </a>
            <x-back-button :href="route('pharmacy.dashboard')" label="Back to Pharmacy Dashboard" />
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead>
                <tr class="bg-gray-50 text-left text-gray-600">
                    <th class="px-4 py-3 whitespace-nowrap">Name</th>
                    <th class="px-4 py-3 whitespace-nowrap">Items</th>
                    <th class="px-4 py-3 whitespace-nowrap">Scheduled</th>
                    <th class="px-4 py-3 whitespace-nowrap">Completed</th>
                    <th class="px-4 py-3 whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($counts as $c)
                    <tr class="border-t border-gray-200">
                        <td class="px-4 py-3 font-medium whitespace-nowrap">{{ $c->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $c->items ? $c->items->count() : 0 }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($c->scheduled_at)->format('M d, Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $c->completed_at ? \Carbon\Carbon::parse($c->completed_at)->format('M d, Y') : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($c->completed_at)
                                <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">Completed</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-700">Pending</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('pharmacy.cycle-counts.show', $c->id) }}" class="text-blue-600 hover:text-blue-800 text-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No cycle counts yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
