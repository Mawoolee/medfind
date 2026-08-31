@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Edit Supplier</h1>
        <x-back-button :href="route('pharmacy.suppliers.index')" label="Back to Suppliers" />
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <form action="{{ route('pharmacy.suppliers.update', $supplier->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required class="block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                <input type="text" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" class="block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="3" class="block w-full border border-gray-300 rounded px-3 py-2.5 text-base">{{ old('address', $supplier->address) }}</textarea>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 sm:py-2 rounded min-h-11">Update</button>
                <a href="{{ route('pharmacy.suppliers.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 sm:py-2 rounded min-h-11">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
