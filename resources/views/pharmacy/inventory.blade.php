{{-- resources/views/pharmacy/inventory.blade.php --}}

@extends('layouts.app')

@section('title', 'Manage Inventory')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Manage Inventory</h1>
        <a href="{{ route('pharmacy.dashboard') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('pharmacy.inventory.update') }}" method="POST">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-gray-600">Medicine</th>
                                <th class="px-4 py-3 text-left text-gray-600">Dosage</th>
                                <th class="px-4 py-3 text-left text-gray-600">Current Stock</th>
                                <th class="px-4 py-3 text-left text-gray-600">Price</th>
                                <th class="px-4 py-3 text-left text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($inventory as $item)
                                <tr class="border-t border-gray-200">
                                    <td class="px-4 py-3">{{ $item->medicine->medicine_name }}</td>
                                    <td class="px-4 py-3">{{ $item->medicine->dosage }}</td>
                                    <td class="px-4 py-3">
                                        <input type="number" 
                                               name="stock[{{ $item->id }}]" 
                                               value="{{ $item->stockQuantity }}" 
                                               min="0"
                                               class="w-20 px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" 
                                               name="price[{{ $item->id }}]" 
                                               value="{{ $item->price }}" 
                                               step="0.01"
                                               min="0"
                                               class="w-28 px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="submit" name="update_id" value="{{ $item->id }}" 
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition duration-200">
                                            Update
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-save mr-2"></i>Save All Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection