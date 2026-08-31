@extends('layouts.app')

@section('title', 'ABC/VED Analysis')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📊 ABC/VED Analysis</h1>
        <x-back-button :href="route('pharmacy.dashboard')" label="Back to Pharmacy Dashboard" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="font-semibold text-gray-800 mb-2">ABC (Value-based)</h3>
            <p class="text-sm text-gray-500 mb-3">A = high cost, B = medium, C = low</p>
            <div class="space-y-2">
                <div class="flex justify-between"><span class="text-red-600 font-medium">A (High)</span><span>{{ $abcCounts['A'] }}</span></div>
                <div class="flex justify-between"><span class="text-yellow-600 font-medium">B (Medium)</span><span>{{ $abcCounts['B'] }}</span></div>
                <div class="flex justify-between"><span class="text-green-600 font-medium">C (Low)</span><span>{{ $abcCounts['C'] }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="font-semibold text-gray-800 mb-2">VED (Criticality)</h3>
            <p class="text-sm text-gray-500 mb-3">V = Vital, E = Essential, D = Desirable</p>
            <div class="space-y-2">
                <div class="flex justify-between"><span class="text-red-600 font-medium">V (Vital)</span><span>{{ $vedCounts['V'] }}</span></div>
                <div class="flex justify-between"><span class="text-yellow-600 font-medium">E (Essential)</span><span>{{ $vedCounts['E'] }}</span></div>
                <div class="flex justify-between"><span class="text-green-600 font-medium">D (Desirable)</span><span>{{ $vedCounts['D'] }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="font-semibold text-gray-800 mb-2">Total Inventory Value</h3>
            <p class="text-2xl font-bold text-blue-600">₱{{ number_format($totalValue, 2) }}</p>
            <p class="text-sm text-gray-500 mt-2">{{ $sorted->count() }} items analyzed</p>
        </div>
    </div>

    <!-- ABC-VED Matrix -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">ABC-VED Matrix</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-blue-50 border border-blue-200 rounded p-4 text-center">
                <p class="text-sm text-blue-700 font-medium">Category I (High Priority)</p>
                <p class="text-3xl font-bold text-blue-700">{{ $matrix['I'] }}</p>
            </div>
            <div class="bg-yellow-50 border border-yellow-200 rounded p-4 text-center">
                <p class="text-sm text-yellow-700 font-medium">Category II (Medium Priority)</p>
                <p class="text-3xl font-bold text-yellow-700">{{ $matrix['II'] }}</p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded p-4 text-center">
                <p class="text-sm text-green-700 font-medium">Category III (Low Priority)</p>
                <p class="text-3xl font-bold text-green-700">{{ $matrix['III'] }}</p>
            </div>
        </div>
    </div>

    <!-- Detailed table -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Item Classification Details</h3>
            <div class="overflow-x-auto -mx-6 px-6">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-600">
                            <th class="px-4 py-3 whitespace-nowrap">Medicine</th>
                            <th class="px-4 py-3 whitespace-nowrap">Stock</th>
                            <th class="px-4 py-3 whitespace-nowrap">Price</th>
                            <th class="px-4 py-3 whitespace-nowrap">Value</th>
                            <th class="px-4 py-3 whitespace-nowrap">ABC</th>
                            <th class="px-4 py-3 whitespace-nowrap">VED</th>
                            <th class="px-4 py-3 whitespace-nowrap">ABC-VED</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sorted as $item)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 font-medium whitespace-nowrap">{{ $item->medicine->medicine_name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $item->available_stock }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">₱{{ number_format((float) $item->representative_price, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">₱{{ number_format($item->value, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $item->abc === 'A' ? 'bg-red-100 text-red-700' : ($item->abc === 'B' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">{{ $item->abc }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $item->ved === 'V' ? 'bg-red-100 text-red-700' : ($item->ved === 'E' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">{{ $item->ved }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $item->abc_ved === 'I' ? 'bg-blue-100 text-blue-700' : ($item->abc_ved === 'II' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">Cat {{ $item->abc_ved }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No inventory items to analyze.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
