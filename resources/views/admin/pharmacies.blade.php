{{-- resources/views/admin/pharmacies.blade.php --}}

@extends('layouts.app')

@section('title', 'Manage Pharmacies')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Manage Pharmacies</h1>
        <div class="flex gap-3">
            <a href="{{ route('admin.pharmacy.add') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add Pharmacy
            </a>
            <a href="{{ route('admin.dashboard') }}" class="text-blue-600 hover:text-blue-800">
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
    <form method="GET" action="{{ route('admin.pharmacies') }}" class="bg-white rounded-lg shadow-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or address..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="all">All Statuses</option>
                @foreach(['approved', 'pending', 'rejected'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
            <i class="fas fa-search mr-1"></i>Search
        </button>
        <a href="{{ route('admin.pharmacies') }}" class="text-gray-500 hover:text-gray-700 text-sm px-2 py-2">Reset</a>
    </form>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
<th class="px-4 py-3 text-left text-gray-600">Name</th>
                            <th class="px-4 py-3 text-left text-gray-600">Address</th>
                            <th class="px-4 py-3 text-left text-gray-600">Contact</th>
                            <th class="px-4 py-3 text-left text-gray-600">Owner</th>
                            <th class="px-4 py-3 text-left text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pharmacies as $pharmacy)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 font-semibold">{{ $pharmacy->pharmacy_name }}</td>
                                <td class="px-4 py-3 text-sm">{{ $pharmacy->pharmacyAddress }}</td>
                                <td class="px-4 py-3">{{ $pharmacy->contactNumber }}</td>
<td class="px-4 py-3">{{ $pharmacy->user->name ?? 'No owner' }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs
                                        {{ $pharmacy->status === 'approved' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $pharmacy->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                        {{ $pharmacy->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}">
                                        {{ ucfirst($pharmacy->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-3 items-center">
                                        <a href="{{ route('admin.pharmacy.edit', $pharmacy->id) }}" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if($pharmacy->status !== 'approved')
                                            <form action="{{ route('admin.pharmacy.approve', $pharmacy->id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-green-600 hover:text-green-800" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('admin.pharmacy.delete', $pharmacy->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Remove this pharmacy?')" 
                                                    class="text-red-600 hover:text-red-800">
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
