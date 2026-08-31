@extends('layouts.app')

@section('title', 'New Return / Recall')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6"><div><h1 class="text-2xl font-bold text-gray-800">New Return / Recall</h1><p class="text-sm text-gray-500 mt-1">The requested quantity is removed from available batches in FEFO order.</p></div><x-back-button :href="route('pharmacy.returns.index')" label="Back to Returns and Recalls" /></div>

    @if($errors->any())<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <form method="POST" action="{{ route('pharmacy.returns.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Medicine *</label>
                    <select name="inventory_item_id" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base">
                        <option value="">-- Select --</option>
                        @foreach($inventory as $item)
                            <option value="{{ $item->id }}" {{ (string) old('inventory_item_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->medicine->medicine_name }} @if($item->medicine->dosage)({{ $item->medicine->dosage }})@endif — Available: {{ $item->available_stock }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700">Type *</label><select name="type" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base"><option value="return" {{ old('type') === 'return' ? 'selected' : '' }}>Return to supplier</option><option value="recall" {{ old('type') === 'recall' ? 'selected' : '' }}>Recall</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700">Quantity *</label><input type="number" name="quantity" value="{{ old('quantity') }}" min="1" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base"></div>
                <div><label class="block text-sm font-medium text-gray-700">Reason</label><input type="text" name="reason" value="{{ old('reason') }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base" placeholder="Damaged, defective, batch recall"></div>
            </div>
            <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 sm:py-2 rounded min-h-11">Record Return / Recall</button>
        </form>
    </div>
</div>
@endsection
