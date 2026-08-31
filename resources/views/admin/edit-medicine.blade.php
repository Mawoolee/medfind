{{-- resources/views/admin/edit-medicine.blade.php --}}

@extends('layouts.app')

@section('title', 'Edit Medicine')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Edit Medicine</h1>
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

    <div class="bg-white rounded-lg shadow-lg p-5 sm:p-6 max-w-2xl">
        <form action="{{ route('admin.medicine.update', $medicine->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="medicine_name" class="block text-gray-700 text-sm font-medium mb-2">Medicine Name</label>
                <input type="text" id="medicine_name" name="medicine_name" value="{{ old('medicine_name', $medicine->medicine_name) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="dosage" class="block text-gray-700 text-sm font-medium mb-2">Dosage</label>
                <input type="text" id="dosage" name="dosage" value="{{ old('dosage', $medicine->dosage) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="manufacturer" class="block text-gray-700 text-sm font-medium mb-2">Manufacturer</label>
                <input type="text" id="manufacturer" name="manufacturer" value="{{ old('manufacturer', $medicine->manufacturer) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="category" class="block text-gray-700 text-sm font-medium mb-2">Category</label>
                <input type="text" id="category" name="category" value="{{ old('category', $medicine->category) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label class="flex items-center gap-2 text-gray-700 text-sm font-medium">
                    <input type="checkbox" name="requiresPrescription" value="1" class="w-4 h-4" {{ old('requiresPrescription', $medicine->requiresPrescription) ? 'checked' : '' }}>
                    Requires Prescription (Rx)
                </label>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit" class="inline-flex items-center justify-center bg-purple-600 hover:bg-purple-700 text-white px-6 py-2.5 rounded-lg transition duration-200 w-full sm:w-auto">
                    <i class="fas fa-save mr-2"></i>Update Medicine
                </button>
                <a href="{{ route('admin.medicines') }}" class="inline-flex items-center justify-center bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2.5 rounded-lg transition duration-200 w-full sm:w-auto">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
