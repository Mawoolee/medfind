@extends('layouts.app')

@section('title', 'New Return / Recall')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">↩️ New Return / Recall</h1>
        <a href="{{ route('pharmacy.returns.index') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <form method="POST" action="{{ route('pharmacy.returns.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Medicine *</label>
                    <select name="inventory_item_id" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                        <option value="">-- Select --</option>
                        @foreach($inventory as $inv)
                            <option value="{{ $inv->id }}">{{ $inv->medicine->medicine_name }} @if($inv->medicine->dosage) ({{ $inv->medicine->dosage }}) @endif — Stock: {{ $inv->stockQuantity }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Type *</label>
                    <select name="type" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                        <option value="return">Return (to supplier)</option>
                        <option value="recall">Recall (defective/expired)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Quantity *</label>
                    <input type="number" name="quantity" min="1" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Reason</label>
                    <input type="text" name="reason" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2" placeholder="e.g., Damaged, expired, batch recall">
                </div>
            </div>

            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">Record Return/Recall</button>
        </form>
    </div>
</div>
@endsection
