@extends('layouts.app')

@section('title', 'Log Controlled Substance')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🛡️ Log Controlled Substance</h1>
            <p class="text-sm text-gray-500 mt-1">Record a dispensing, wastage, transfer, or adjustment.</p>
        </div>
        <a href="{{ route('pharmacy.controlled-substances.index') }}" class="text-[#9400D3] hover:text-[#7a00b0] text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Logbook
        </a>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-4 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <form method="POST" action="{{ route('pharmacy.controlled-substances.store') }}">
            @csrf

            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Medicine <span class="text-red-500">*</span></label>
                <select name="inventory_item_id" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
                    <option value="">-- Select a controlled substance --</option>
                    @foreach($controlledItems as $item)
                        <option value="{{ $item->id }}" {{ old('inventory_item_id') == $item->id ? 'selected' : '' }}>
                            {{ $item->medicine?->medicine_name }} — Stock: {{ $item->stockQuantity }}
                            @if($item->batch_number) (Batch: {{ $item->batch_number }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Action <span class="text-red-500">*</span></label>
                <select name="action" required id="action-select"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
                    <option value="">-- Select action --</option>
                    <option value="dispensed"  {{ old('action') === 'dispensed'  ? 'selected' : '' }}>Dispensed to patient</option>
                    <option value="wastage"    {{ old('action') === 'wastage'    ? 'selected' : '' }}>Wastage / Destroyed</option>
                    <option value="transferred"{{ old('action') === 'transferred'? 'selected' : '' }}>Transferred to branch</option>
                    <option value="adjustment" {{ old('action') === 'adjustment' ? 'selected' : '' }}>Manual adjustment</option>
                </select>
                <p class="text-xs text-gray-400 mt-1" id="action-hint">Dispensed, wastage, and transferred will <strong>decrease</strong> stock. Adjustment will set the entered quantity directly.</p>
            </div>

            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity <span class="text-red-500">*</span></label>
                <input type="number" name="quantity" value="{{ old('quantity') }}" min="1" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30"
                    placeholder="Enter quantity">
            </div>

            <div class="mb-5" id="patient-ref-field" style="display:none;">
                <label class="block text-sm font-medium text-gray-700 mb-1">Patient / Prescription Reference</label>
                <input type="text" name="patient_reference" value="{{ old('patient_reference') }}" maxlength="255"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30"
                    placeholder="e.g. Rx #12345 or Patient name">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30"
                    placeholder="Additional notes, reason, or reference...">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-[#9400D3] text-white py-2 rounded-lg font-semibold text-sm hover:bg-[#7a00b0] transition">
                    <i class="fas fa-save mr-2"></i>Submit Log Entry
                </button>
                <a href="{{ route('pharmacy.controlled-substances.index') }}"
                    class="flex-1 text-center border border-gray-300 text-gray-700 py-2 rounded-lg font-semibold text-sm hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const actionSelect = document.getElementById('action-select');
    const patientField = document.getElementById('patient-ref-field');
    const hint = document.getElementById('action-hint');

    function updateHint() {
        const val = actionSelect.value;
        patientField.style.display = val === 'dispensed' ? 'block' : 'none';
        if (val === 'adjustment') {
            hint.innerHTML = '<strong>Adjustment</strong>: the entered quantity will be used to update the stock level directly. Use this for physical count corrections.';
        } else {
            hint.innerHTML = 'Dispensed, wastage, and transferred will <strong>decrease</strong> stock. Adjustment will set the entered quantity directly.';
        }
    }

    actionSelect.addEventListener('change', updateHint);
    updateHint();
</script>
@endpush
@endsection
