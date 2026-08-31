{{-- resources/views/admin/medicines.blade.php --}}

@extends('layouts.app')

@section('title', 'Manage Medicines')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Manage Medicines</h1>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.medicine.add') }}" class="inline-flex items-center justify-center bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-lg transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add Medicine
            </a>
            <a href="{{ route('admin.dashboard') }}" class="text-blue-600 hover:text-blue-800 whitespace-nowrap">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
        </div>
    </div>

@if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    <!-- Search & Filter -->
    <form method="GET" action="{{ route('admin.medicines') }}" class="bg-white rounded-lg shadow-lg p-4 mb-6 flex flex-col sm:flex-row flex-wrap gap-3 sm:items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-600 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, manufacturer, category..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base sm:text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-1">Category</label>
            <select name="category" class="w-full sm:w-auto border border-gray-300 rounded-lg px-3 py-2.5 text-base sm:text-sm">
                <option value="all">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}" {{ request('category') === $category ? 'selected' : '' }}>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
            <i class="fas fa-search mr-1"></i>Search
        </button>
        <a href="{{ route('admin.medicines') }}" class="text-gray-500 hover:text-gray-700 text-sm px-2 py-2 text-center">Reset</a>
    </form>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-4 sm:p-6">
            <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                <table class="w-full min-w-[760px]">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Medicine Name</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Dosage</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Manufacturer</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Category</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Prescription</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($medicines as $medicine)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 text-sm font-semibold">{{ $medicine->medicine_name }}</td>
                                <td class="px-4 py-3 text-sm">{{ $medicine->dosage }}</td>
                                <td class="px-4 py-3 text-sm">{{ $medicine->manufacturer }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if($medicine->category)
                                        <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-700">{{ $medicine->category }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($medicine->requiresPrescription)
                                        <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700 whitespace-nowrap">Rx Required</span>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">OTC</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-4 items-center">
                                        <a href="{{ route('admin.medicine.edit', $medicine->id) }}" class="text-blue-600 hover:text-blue-800 text-base py-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.medicine.delete', $medicine->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Delete this medicine?')" class="text-red-600 hover:text-red-800 text-base py-1">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No medicines found.</td>
                            </tr>
@endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $medicines->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
