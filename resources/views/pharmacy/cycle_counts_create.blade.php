@extends('layouts.app')

@section('title', 'New Cycle Count')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📋 New Cycle Count</h1>
        <x-back-button :href="route('pharmacy.cycle-counts.index')" label="Back to Cycle Counts" />
    </div>

    @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <form method="POST" action="{{ route('pharmacy.cycle-counts.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Count Name *</label>
                    <input type="text" name="name" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2" placeholder="e.g., Shelf-A Weekly Count">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Scheduled For</label>
                    <input type="date" name="scheduled_at" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2" value="{{ now()->format('Y-m-d') }}">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2"></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Items to Count</label>
                <div class="max-h-72 overflow-y-auto border border-gray-200 rounded">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 text-left text-gray-600 text-sm">
                                <th class="px-3 py-2 w-8"></th>
                                <th class="px-3 py-2">Medicine</th>
                                <th class="px-3 py-2 w-24">Current Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inventory as $inv)
                                <tr class="border-t border-gray-200">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" name="items[]" value="{{ $inv->id }}" class="item-check">
                                    </td>
                                    <td class="px-3 py-2">{{ $inv->medicine->medicine_name }} @if($inv->medicine->dosage) ({{ $inv->medicine->dosage }}) @endif</td>
                                    <td class="px-3 py-2">{{ $inv->available_stock }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-4 text-center text-gray-500">No inventory items.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">Create Cycle Count</button>
<button type="button" onclick="document.querySelectorAll('.item-check').forEach(c=>c.checked=true)" class="text-blue-600 text-sm">Select All</button>
            </div>
        </form>
    </div>
</div>
@endsection
