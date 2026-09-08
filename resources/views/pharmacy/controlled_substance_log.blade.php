@extends('layouts.app')

@section('title', 'Log Controlled Substance')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div><h1 class="text-2xl font-bold text-gray-800">Log Controlled Substance</h1><p class="text-sm text-gray-500 mt-1">Stock decreases are allocated to available batches in FEFO order.</p></div>
        <x-back-button :href="route('pharmacy.dashboard')" label="Back to Dashboard" />
    </div>

    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-4 text-sm"><ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <form method="POST" action="{{ route('pharmacy.controlled-substances.store') }}">
            @csrf
            <div class="mb-5">
                <label for="inventory_item_id_text" class="block text-sm font-medium text-gray-700 mb-1">Medicine <span class="text-red-500">*</span></label>
                <div style="position:relative">
                    <input
                        type="text"
                        id="inventory_item_id_text"
                        autocomplete="off"
                        placeholder="Type to search medicine..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base"
                    >
                    <input type="hidden" name="inventory_item_id" id="inventory_item_id_hidden" value="{{ old('inventory_item_id') }}">
                    <ul id="inventory_item_id_list" style="display:none;position:absolute;z-index:50;background:white;border:1px solid #d1d5db;border-radius:6px;max-height:200px;overflow-y:auto;width:100%;margin-top:2px;list-style:none;padding:0;margin-left:0;"></ul>
                </div>
            </div>
            <div class="mb-5">
                <label for="action-select" class="block text-sm font-medium text-gray-700 mb-1">Action <span class="text-red-500">*</span></label>
                <select name="action" required id="action-select" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base">
                    <option value="">-- Select action --</option>
                    <option value="dispensed" {{ old('action') === 'dispensed' ? 'selected' : '' }}>Dispensed to patient</option>
                    <option value="wastage" {{ old('action') === 'wastage' ? 'selected' : '' }}>Wastage / Destroyed</option>
                    <option value="transferred" {{ old('action') === 'transferred' ? 'selected' : '' }}>Transferred to branch</option>
                    <option value="adjustment" {{ old('action') === 'adjustment' ? 'selected' : '' }}>Set lower stock total</option>
                </select>
                <p class="text-xs text-gray-500 mt-1" id="action-hint">Dispensing, wastage, and transfer subtract the entered quantity using FEFO.</p>
            </div>
            <div class="mb-5">
                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity <span class="text-red-500">*</span></label>
                <input id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base" placeholder="Enter quantity">
            </div>
            <div class="mb-5" id="patient-ref-field" style="display:none;">
                <label class="block text-sm font-medium text-gray-700 mb-1">Patient / Prescription Reference</label>
                <input type="text" name="patient_reference" value="{{ old('patient_reference') }}" maxlength="255" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base" placeholder="Rx # or patient reference">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base" placeholder="Reason or reference">{{ old('notes') }}</textarea>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit" class="flex-1 bg-purple-700 text-white py-3 rounded-lg font-semibold text-sm hover:bg-purple-800 min-h-11"><i class="fas fa-save mr-2"></i>Submit Entry</button>
                <a href="{{ route('pharmacy.controlled-substances.index') }}" class="flex-1 inline-flex items-center justify-center text-center border border-gray-300 text-gray-700 py-3 rounded-lg font-semibold text-sm hover:bg-gray-50 min-h-11">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// Medicine searchable combobox
(() => {
    const medOptions = @json($controlledItems->map(fn($item) => [
        'id'   => $item->id,
        'name' => ($item->medicine?->medicine_name ?? '(unknown)') . ' — Available: ' . $item->available_stock,
    ])->values());

    const textInput   = document.getElementById('inventory_item_id_text');
    const hiddenInput = document.getElementById('inventory_item_id_hidden');
    const dropList    = document.getElementById('inventory_item_id_list');

    // Pre-fill text from old() value
    const oldId = hiddenInput.value;
    if (oldId) {
        const found = medOptions.find(m => String(m.id) === String(oldId));
        if (found) textInput.value = found.name;
    }

    function renderList(items) {
        dropList.innerHTML = '';
        if (!items.length) {
            if (medOptions.length === 0) {
                const li = document.createElement('li');
                li.textContent = 'No controlled substances in stock.';
                li.style.cssText = 'padding:8px 12px;font-size:0.875rem;color:#6b7280;font-style:italic;';
                dropList.appendChild(li);
                dropList.style.display = 'block';
            } else {
                dropList.style.display = 'none';
            }
            return;
        }
        items.forEach(m => {
            const li = document.createElement('li');
            li.textContent = m.name;
            li.dataset.id  = m.id;
            li.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:0.875rem;';
            li.addEventListener('mousedown', e => {
                e.preventDefault();
                textInput.value   = m.name;
                hiddenInput.value = m.id;
                dropList.style.display = 'none';
            });
            li.addEventListener('mouseover', () => li.style.background = '#f3f4f6');
            li.addEventListener('mouseout',  () => li.style.background = '');
            dropList.appendChild(li);
        });
        dropList.style.display = 'block';
    }

    textInput.addEventListener('input', () => {
        hiddenInput.value = '';
        const q = textInput.value.trim().toLowerCase();
        renderList(q ? medOptions.filter(m => m.name.toLowerCase().includes(q)) : medOptions);
    });

    textInput.addEventListener('focus', () => {
        const q = textInput.value.trim().toLowerCase();
        renderList(q ? medOptions.filter(m => m.name.toLowerCase().includes(q)) : medOptions);
    });

    textInput.addEventListener('click', () => {
        const q = textInput.value.trim().toLowerCase();
        renderList(q ? medOptions.filter(m => m.name.toLowerCase().includes(q)) : medOptions);
    });

    textInput.addEventListener('blur', () => {
        setTimeout(() => {
            dropList.style.display = 'none';
            if (!hiddenInput.value) textInput.value = '';
        }, 150);
    });

    document.addEventListener('click', e => {
        if (!textInput.contains(e.target) && !dropList.contains(e.target)) {
            dropList.style.display = 'none';
        }
    });

    const form = textInput.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!hiddenInput.value) {
                e.preventDefault();
                textInput.classList.add('border-red-500');
                let errEl = document.getElementById('medicine-combo-error');
                if (!errEl) {
                    errEl = document.createElement('p');
                    errEl.id = 'medicine-combo-error';
                    errEl.className = 'mt-1 text-sm text-red-600';
                    errEl.textContent = 'Please select a medicine from the list.';
                    textInput.closest('div').parentNode.appendChild(errEl);
                }
                textInput.focus();
            }
        });
        textInput.addEventListener('input', function() {
            textInput.classList.remove('border-red-500');
            const e2 = document.getElementById('medicine-combo-error');
            if (e2) e2.remove();
        });
    }
})();

// Action hint JS
const actionSelect = document.getElementById('action-select');
const patientField = document.getElementById('patient-ref-field');
const hint = document.getElementById('action-hint');
function updateHint() {
    patientField.style.display = actionSelect.value === 'dispensed' ? 'block' : 'none';
    hint.textContent = actionSelect.value === 'adjustment'
        ? 'Enter the target total. It may only reduce stock; increases must use Add Stock / Receive Delivery.'
        : 'The entered quantity will be deducted from available batches in FEFO order.';
}
actionSelect.addEventListener('change', updateHint);
updateHint();
</script>
@endpush
@endsection
