@extends('layouts.app')

@section('title', 'Pharmacy Profile')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">🏥 Pharmacy Profile</h1>
        <a href="{{ route('pharmacy.dashboard') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <form method="POST" action="{{ route('pharmacy.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Pharmacy Name *</label>
                    <input type="text" name="pharmacy_name" value="{{ old('pharmacy_name', $pharmacy->pharmacy_name) }}" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                    <input type="text" name="contactNumber" value="{{ old('contactNumber', $pharmacy->contactNumber) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                        <option value="active" {{ $pharmacy->status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $pharmacy->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Address</label>
                    <textarea name="pharmacyAddress" rows="2" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">{{ old('pharmacyAddress', $pharmacy->pharmacyAddress) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Latitude</label>
                    <input type="number" step="any" name="latitude" value="{{ old('latitude', $pharmacy->latitude) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Longitude</label>
                    <input type="number" step="any" name="longitude" value="{{ old('longitude', $pharmacy->longitude) }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                </div>
            </div>

            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">Save Profile</button>
        </form>
    </div>
</div>
@endsection
