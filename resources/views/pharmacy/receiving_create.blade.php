@extends('layouts.app')

@section('title', 'Add Stock / Receive Delivery')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add Stock / Receive Delivery</h1>
            <p class="text-sm text-gray-500 mt-1">Each row creates a new, traceable stock batch for an existing medicine.</p>
        </div>
        <x-back-button :href="route('pharmacy.inventory')" label="Back to Inventory" />
    </div>

    @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($inventory->isEmpty())
        <div class="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg p-5">
            Add a medicine master before receiving stock.
            <a href="{{ route('pharmacy.inventory.create') }}" class="font-semibold underline">Add New Medicine</a>
        </div>
    @else
        <div class="bg-white rounded-lg shadow-lg p-6">
            <form id="receiving-form" action="{{ route('pharmacy.receiving.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base @error('supplier_id') border-red-500 @enderror">
                            <option value="">-- Select supplier --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ (string) old('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="purchase_order" class="block text-sm font-medium text-gray-700">Purchase Order / Delivery Reference</label>
                        <input id="purchase_order" type="text" name="purchase_order" value="{{ old('purchase_order') }}" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2.5 text-base" placeholder="PO-0001 / Delivery receipt">
                    </div>
                </div>

                <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Received Batches</h2>
                        <p class="text-sm text-gray-500">A duplicate batch-and-lot combination for the same medicine will be rejected, never overwritten.</p>
                    </div>
                    <button type="button" id="add-item" class="w-full sm:w-auto inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 min-h-11 rounded text-sm"><i class="fas fa-plus mr-1"></i>Add another batch</button>
                </div>

                @php
                    $defaultRows = [[
                        'inventory_item_id' => $selectedInventoryId,
                        'received_date' => now()->format('Y-m-d'),
                    ]];
                    $rows = old('items', $defaultRows);
                @endphp

                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[1100px]" id="items-table">
                        <thead>
                            <tr class="bg-gray-50 text-left text-gray-600">
                                <th class="px-2 py-2">Existing Medicine *</th>
                                <th class="px-2 py-2">Batch # *</th>
                                <th class="px-2 py-2">Lot #</th>
                                <th class="px-2 py-2">Quantity *</th>
                                <th class="px-2 py-2">Price *</th>
                                <th class="px-2 py-2">Expiry</th>
                                <th class="px-2 py-2">Date Received *</th>
                                <th class="px-2 py-2 text-center">Cold Chain</th>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody id="items-list">
                            @foreach($rows as $index => $row)
                                <tr class="item-row border-t border-gray-200">
                                    <td class="px-2 py-2">
                                        <select name="items[{{ $index }}][inventory_item_id]" required class="medicine-select w-56 border border-gray-300 rounded px-2 py-1.5 text-base">
                                            <option value="">-- Select medicine --</option>
                                            @foreach($inventory as $aggregate)
                                                <option value="{{ $aggregate->id }}" data-cold-chain-required="{{ $aggregate->medicine->cold_chain_required ? '1' : '0' }}" {{ (string) ($row['inventory_item_id'] ?? '') === (string) $aggregate->id ? 'selected' : '' }}>
                                                    {{ $aggregate->medicine->medicine_name }}@if($aggregate->medicine->brand_name) — {{ $aggregate->medicine->brand_name }}@endif @if($aggregate->medicine->dosage) ({{ $aggregate->medicine->dosage }})@endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-2 py-2"><input type="text" name="items[{{ $index }}][batch_number]" value="{{ $row['batch_number'] ?? '' }}" required class="w-28 border border-gray-300 rounded px-2 py-1.5 text-base" placeholder="B001"></td>
                                    <td class="px-2 py-2"><input type="text" name="items[{{ $index }}][lot_number]" value="{{ $row['lot_number'] ?? '' }}" class="w-28 border border-gray-300 rounded px-2 py-1.5 text-base" placeholder="LOT-01"></td>
                                    <td class="px-2 py-2"><input type="number" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? '' }}" required min="1" class="w-24 border border-gray-300 rounded px-2 py-1.5 text-base"></td>
                                    <td class="px-2 py-2"><input type="number" step="0.01" name="items[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" required min="0" class="w-28 border border-gray-300 rounded px-2 py-1.5 text-base"></td>
                                    <td class="px-2 py-2"><input type="date" name="items[{{ $index }}][expiry_date]" value="{{ $row['expiry_date'] ?? '' }}" class="w-36 border border-gray-300 rounded px-2 py-1.5 text-base"></td>
                                    <td class="px-2 py-2"><input type="date" name="items[{{ $index }}][received_date]" value="{{ $row['received_date'] ?? now()->format('Y-m-d') }}" required data-default-date="{{ now()->format('Y-m-d') }}" class="w-36 border border-gray-300 rounded px-2 py-1.5 text-base"></td>
                                    <td class="px-2 py-2 text-center">
                                        <input type="hidden" name="items[{{ $index }}][cold_chain]" value="0">
                                        <input type="checkbox" name="items[{{ $index }}][cold_chain]" value="1" {{ ! empty($row['cold_chain']) ? 'checked' : '' }} class="cold-chain-toggle">
                                    </td>
                                    <td class="px-2 py-2 text-center"><button type="button" class="remove-item text-red-500 hover:text-red-700" title="Remove row"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row sm:items-center gap-3">
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg min-h-11"><i class="fas fa-check mr-2"></i>Process Delivery</button>
                    <a href="{{ route('pharmacy.inventory') }}" class="w-full sm:w-auto inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg min-h-11">Cancel</a>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(() => {
    const list = document.getElementById('items-list');
    const addButton = document.getElementById('add-item');
    if (!list || !addButton) return;

    function reindexNames() {
        list.querySelectorAll('.item-row').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(input => {
                input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
        });
    }

    function syncColdChain(row) {
        const select = row.querySelector('.medicine-select');
        const checkbox = row.querySelector('.cold-chain-toggle');
        const required = select?.selectedOptions[0]?.dataset.coldChainRequired === '1';
        if (required && checkbox) checkbox.checked = true;
        if (checkbox) checkbox.title = required ? 'Required by the medicine master' : 'Mark if this batch requires cold-chain handling';
    }

    addButton.addEventListener('click', () => {
        const newRow = list.querySelector('.item-row').cloneNode(true);
        newRow.querySelectorAll('input').forEach(input => {
            if (input.type === 'hidden') input.value = '0';
            else if (input.type === 'checkbox') input.checked = false;
            else if (input.type === 'date' && input.dataset.defaultDate) input.value = input.dataset.defaultDate;
            else input.value = '';
        });
        newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
        list.appendChild(newRow);
        reindexNames();
        syncColdChain(newRow);
    });

    list.addEventListener('click', event => {
        const button = event.target.closest('.remove-item');
        if (!button || list.querySelectorAll('.item-row').length === 1) return;
        button.closest('.item-row').remove();
        reindexNames();
    });

    list.addEventListener('change', event => {
        if (event.target.classList.contains('medicine-select')) syncColdChain(event.target.closest('.item-row'));
    });

    list.querySelectorAll('.item-row').forEach(syncColdChain);
})();
</script>
@endpush
