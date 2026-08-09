{{-- resources/views/admin/edit-pharmacy.blade.php --}}

@extends('layouts.app')

@section('title', 'Edit Pharmacy')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Edit Pharmacy</h1>
        <a href="{{ route('admin.pharmacies') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6 max-w-2xl">
        <form action="{{ route('admin.pharmacy.update', $pharmacy->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="pharmacy_name" class="block text-gray-700 font-medium mb-2">Pharmacy Name</label>
                <input type="text" id="pharmacy_name" name="pharmacy_name" value="{{ old('pharmacy_name', $pharmacy->pharmacy_name) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label for="pharmacyAddress" class="block text-gray-700 font-medium mb-2">Address</label>
                <input type="text" id="pharmacyAddress" name="pharmacyAddress" value="{{ old('pharmacyAddress', $pharmacy->pharmacyAddress) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label for="contactNumber" class="block text-gray-700 font-medium mb-2">Contact Number</label>
                <input type="text" id="contactNumber" name="contactNumber" value="{{ old('contactNumber', $pharmacy->contactNumber) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="latitude" class="block text-gray-700 font-medium mb-2">Latitude</label>
                    <input type="number" step="any" id="latitude" name="latitude" value="{{ old('latitude', $pharmacy->latitude) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="longitude" class="block text-gray-700 font-medium mb-2">Longitude</label>
                    <input type="number" step="any" id="longitude" name="longitude" value="{{ old('longitude', $pharmacy->longitude) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="mb-4">
                <label for="user_id" class="block text-gray-700 font-medium mb-2">Owner</label>
                <select id="user_id" name="user_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- No owner --</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" {{ old('user_id', $pharmacy->user_id) == $user->id ? 'selected' : '' }}>
                            {{ $user->name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-4">
                <label for="status" class="block text-gray-700 font-medium mb-2">Status</label>
                <select id="status" name="status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @foreach (['pending', 'approved', 'rejected'] as $status)
                        <option value="{{ $status }}" {{ old('status', $pharmacy->status) === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-save mr-2"></i>Update Pharmacy
                </button>
                <a href="{{ route('admin.pharmacies') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition duration-200">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
