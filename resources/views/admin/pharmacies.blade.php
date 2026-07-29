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
                                    <form action="{{ route('admin.pharmacy.delete', $pharmacy->id) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Remove this pharmacy?')" 
                                                class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection