{{-- resources/views/admin/add-medicine.blade.php --}}

@extends('layouts.app')

@section('title', 'Add Medicine')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Add Medicine</h1>
        <a href="{{ route('admin.medicines') }}" class="text-blue-600 hover:text-blue-800">
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
        <form action="{{ route('admin.medicine.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="medicine_name" class="block text-gray-700 font-medium mb-2">Medicine Name</label>
                <input type="text" id="medicine_name" name="medicine_name" value="{{ old('medicine_name') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="dosage" class="block text-gray-700 font-medium mb-2">Dosage</label>
                <input type="text" id="dosage" name="dosage" value="{{ old('dosage') }}" placeholder="e.g. 500mg"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="manufacturer" class="block text-gray-700 font-medium mb-2">Manufacturer</label>
                <input type="text" id="manufacturer" name="manufacturer" value="{{ old('manufacturer') }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="category" class="block text-gray-700 font-medium mb-2">Category</label>
                <input type="text" id="category" name="category" value="{{ old('category') }}" placeholder="e.g. Antibiotic"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label class="flex items-center gap-2 text-gray-700 font-medium">
                    <input type="checkbox" name="requiresPrescription" value="1" {{ old('requiresPrescription') ? 'checked' : '' }}>
                    Requires Prescription (Rx)
                </label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add Medicine
                </button>
                <a href="{{ route('admin.medicines') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition duration-200">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
