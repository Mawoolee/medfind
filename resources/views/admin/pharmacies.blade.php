{{-- resources/views/admin/pharmacies.blade.php --}}

@extends('layouts.app')

@section('title', 'Manage Pharmacies')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Manage Pharmacies</h1>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.pharmacy.add') }}" class="inline-flex items-center justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add Pharmacy
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
    <form method="GET" action="{{ route('admin.pharmacies') }}" class="bg-white rounded-lg shadow-lg p-4 mb-6 flex flex-col sm:flex-row flex-wrap gap-3 sm:items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-600 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or address..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base sm:text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-1">Status</label>
            <select name="status" class="w-full sm:w-auto border border-gray-300 rounded-lg px-3 py-2.5 text-base sm:text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400">
                <option value="all">All Statuses</option>
                @foreach(['approved', 'pending', 'rejected'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
            <i class="fas fa-search mr-1"></i>Search
        </button>
        <a href="{{ route('admin.pharmacies') }}" class="text-gray-500 hover:text-gray-700 text-sm px-2 py-2 text-center">Reset</a>
    </form>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-4 sm:p-6">
            <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                <table class="w-full min-w-[780px]">
                    <thead>
                        <tr class="bg-gray-50">
<th class="px-4 py-3 text-left text-sm text-gray-600">Name</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Address</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Contact</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Owner</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pharmacies as $pharmacy)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 text-sm font-semibold">{{ $pharmacy->pharmacy_name }}</td>
                                <td class="px-4 py-3 text-sm">{{ $pharmacy->pharmacyAddress }}</td>
                                <td class="px-4 py-3 text-sm">{{ $pharmacy->contactNumber }}</td>
<td class="px-4 py-3 text-sm">{{ $pharmacy->user->name ?? 'No owner' }}</td>
                                <td class="px-4 py-3">
                                    <div x-data="{ open: false }" class="relative inline-block">
                                        <button @click="open = !open" type="button"
                                            class="text-xs border-2 rounded-full px-4 py-1.5 font-semibold cursor-pointer focus:outline-none inline-flex items-center gap-1
                                                {{ $pharmacy->status === 'approved' ? 'border-green-400 bg-green-50 text-green-700' : '' }}
                                                {{ $pharmacy->status === 'pending'  ? 'border-yellow-400 bg-yellow-50 text-yellow-700' : '' }}
                                                {{ $pharmacy->status === 'rejected' ? 'border-red-400 bg-red-50 text-red-700' : '' }}">
                                            {{ ucfirst($pharmacy->status) }}
                                            <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div x-show="open" @click.away="open = false" x-cloak
                                             class="absolute left-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50 w-32">
                                            @foreach(['pending', 'approved', 'rejected'] as $statusOption)
                                                @if($statusOption !== $pharmacy->status)
                                                    <form action="{{ route('admin.pharmacy.update', $pharmacy->id) }}" method="POST">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="pharmacy_name"   value="{{ $pharmacy->pharmacy_name }}">
                                                        <input type="hidden" name="pharmacyAddress" value="{{ $pharmacy->pharmacyAddress }}">
                                                        <input type="hidden" name="latitude"        value="{{ $pharmacy->latitude }}">
                                                        <input type="hidden" name="longitude"       value="{{ $pharmacy->longitude }}">
                                                        <input type="hidden" name="contactNumber"   value="{{ $pharmacy->contactNumber }}">
                                                        <input type="hidden" name="user_id"         value="{{ $pharmacy->user_id }}">
                                                        <input type="hidden" name="status"          value="{{ $statusOption }}">
                                                        <button type="submit" class="w-full text-left px-3 py-1.5 text-xs font-medium hover:bg-gray-50 transition
                                                            {{ $statusOption === 'approved' ? 'text-green-700' : '' }}
                                                            {{ $statusOption === 'pending'  ? 'text-yellow-700' : '' }}
                                                            {{ $statusOption === 'rejected' ? 'text-red-700' : '' }}">
                                                            {{ ucfirst($statusOption) }}
                                                        </button>
                                                    </form>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-4 items-center">
                                        <a href="{{ route('admin.pharmacy.edit', $pharmacy->id) }}" class="text-blue-600 hover:text-blue-800 text-base py-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.pharmacy.delete', $pharmacy->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Remove this pharmacy?')"
                                                    class="text-red-600 hover:text-red-800 text-base py-1">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
@endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $pharmacies->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
