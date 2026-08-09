@extends('layouts.app')

@section('title', 'Receive Shipment')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📥 Receive Shipment</h1>
        <a href="{{ route('pharmacy.inventory') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Inventory
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <form id="receiving-form" action="{{ route('pharmacy.receiving.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Supplier</label>
                    <select name="supplier_id" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
                        <option value="">-- Select supplier --</option>
                        @foreach($suppliers as $sp)
                            <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Purchase Order / Reference</label>
                    <input type="text" name="purchase_order" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2" placeholder="PO-0001 / Delivery #">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Barcode (scan or type)</label>
                    <input type="text" id="barcode-input" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2" placeholder="Scan barcode/RFID..." autocomplete="off">
                    <p class="text-xs text-gray-500 mt-1">Scan barcode to auto-fill medicine name.</p>
                </div>
            </div>

            <h4 class="text-lg font-semibold text-gray-800 mb-3">Items</h4>
            <p class="text-sm text-gray-600 mb-3">Verify delivery against purchase order. Controlled substances must be logged separately and stored securely.</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="items-table">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-600">
                            <th class="px-3 py-2">Medicine</th>
                            <th class="px-3 py-2">Qty</th>
                            <th class="px-3 py-2">Price</th>
                            <th class="px-3 py-2">Batch #</th>
                            <th class="px-3 py-2">Expiry</th>
                            <th class="px-3 py-2">Cold Chain</th>
                            <th class="px-3 py-2">Controlled</th>
                            <th class="px-3 py-2">Category</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="items-list">
                        <tr class="item-row border-t border-gray-200">
                            <td class="px-3 py-2">
                                <input list="inventory-medicines" type="text" name="items[0][medicine_name]" class="w-full border border-gray-300 rounded px-2 py-1" placeholder="Medicine name" required>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" name="items[0][quantity]" class="w-20 border border-gray-300 rounded px-2 py-1" placeholder="Qty" required min="1">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" name="items[0][price]" class="w-24 border border-gray-300 rounded px-2 py-1" placeholder="Price">
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" name="items[0][batch_number]" class="w-24 border border-gray-300 rounded px-2 py-1" placeholder="Batch #">
                            </td>
                            <td class="px-3 py-2">
                                <input type="date" name="items[0][expiry_date]" class="w-32 border border-gray-300 rounded px-2 py-1">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input type="checkbox" name="items[0][cold_chain]" value="1" class="cold-chain-toggle">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input type="checkbox" name="items[0][is_controlled]" value="1" class="controlled-toggle">
                            </td>
                            <td class="px-3 py-2">
                                <select name="items[0][category]" class="w-32 border border-gray-300 rounded px-2 py-1">
                                    <option value="">--</option>
                                    <option value="analgesic">Analgesic</option>
                                    <option value="antibiotic">Antibiotic</option>
                                    <option value="antidiarrheal">Antidiarrheal</option>
                                    <option value="antihistamine">Antihistamine</option>
                                    <option value="nsaid">NSAID</option>
                                    <option value="controlled">Controlled</option>
                                    <option value="vitamin">Vitamin</option>
                                    <option value="supplement">Supplement</option>
                                    <option value="other">Other</option>
                                </select>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" class="remove-item text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <datalist id="inventory-medicines">
                @foreach($inventory as $inv)
                    <option value="{{ $inv->medicine->medicine_name }}"></option>
                @endforeach
            </datalist>

            <div class="mt-4 flex items-center gap-2">
                <button type="button" id="add-item" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded text-sm">
                    <i class="fas fa-plus mr-1"></i>Add another item
                </button>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg">
                    <i class="fas fa-check mr-2"></i>Process Shipment
                </button>
                <a href="{{ route('pharmacy.inventory') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    let idx = 1;
    const list = document.getElementById('items-list');

    function reindexNames() {
        const rows = list.querySelectorAll('.item-row');
        rows.forEach(function(row, i){
            row.querySelectorAll('[name]').forEach(function(input){
                const base = input.name.replace(/items\[\d+\]/, '');
                input.name = 'items[' + i + ']' + base;
            });
        });
    }

    document.getElementById('add-item').addEventListener('click', function(){
        const tpl = document.querySelector('.item-row');
        const newRow = tpl.cloneNode(true);
        newRow.querySelectorAll('input').forEach(function(input){ input.value = ''; });
        const sel = newRow.querySelector('select');
        if (sel) sel.selectedIndex = 0;
        list.appendChild(newRow);
        reindexNames();
    });

    // Remove row (keep at least one)
    list.addEventListener('click', function(e){
        const btn = e.target.closest('.remove-item');
        if (!btn) return;
        const rows = list.querySelectorAll('.item-row');
        if (rows.length <= 1) { return; }
        btn.closest('.item-row').remove();
        reindexNames();
    });

    // Barcode auto-fill: on Enter, fill the first empty medicine name field.
    const barcodeInput = document.getElementById('barcode-input');
    barcodeInput.addEventListener('keydown', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = barcodeInput.value.trim();
            if (!val) return;
            const rows = list.querySelectorAll('.item-row');
            for (const row of rows) {
                const nameInput = row.querySelector('input[name*="medicine_name"]');
                if (nameInput && !nameInput.value) {
                    nameInput.value = val;
                    barcodeInput.value = '';
                    break;
                }
            }
        }
    });

    // Controlled toggle: if checked, default category to controlled.
    list.addEventListener('change', function(e){
        if (e.target.classList && e.target.classList.contains('controlled-toggle') && e.target.checked) {
            const row = e.target.closest('.item-row');
            const cat = row.querySelector('select[name*="category"]');
            if (cat) cat.value = 'controlled';
        }
    });
})();
</script>
@endpush
