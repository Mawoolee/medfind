@extends('layouts.app')

@section('title', 'Log Controlled Substance')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <div><h1 class="text-2xl font-bold text-gray-800">Log Controlled Substance</h1><p class="text-sm text-gray-500 mt-1">Stock decreases are allocated to available batches in FEFO order.</p></div>
        <a href="{{ route('pharmacy.controlled-substances.index') }}" class="text-purple-700 hover:text-purple-900 text-sm font-medium"><i class="fas fa-arrow-left mr-2"></i>Back to Logbook</a>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-4 text-sm"><ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6">
        <form method="POST" action="{{ route('pharmacy.controlled-substances.store') }}">
            @csrf
            <div class="mb-5">
                <label for="inventory_item_id" class="block text-sm font-medium text-gray-700 mb-1">Medicine <span class="text-red-500">*</span></label>
                <select id="inventory_item_id" name="inventory_item_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- Select a controlled substance --</option>
                    @foreach($controlledItems as $item)
                        <option value="{{ $item->id }}" {{ (string) old('inventory_item_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->medicine?->medicine_name }} — Available: {{ $item->available_stock }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-5">
                <label for="action-select" class="block text-sm font-medium text-gray-700 mb-1">Action <span class="text-red-500">*</span></label>
                <select name="action" required id="action-select" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
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
                <input id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Enter quantity">
            </div>
            <div class="mb-5" id="patient-ref-field" style="display:none;">
                <label class="block text-sm font-medium text-gray-700 mb-1">Patient / Prescription Reference</label>
                <input type="text" name="patient_reference" value="{{ old('patient_reference') }}" maxlength="255" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Rx # or patient reference">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Reason or reference">{{ old('notes') }}</textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-purple-700 text-white py-2 rounded-lg font-semibold text-sm hover:bg-purple-800"><i class="fas fa-save mr-2"></i>Submit Entry</button>
                <a href="{{ route('pharmacy.controlled-substances.index') }}" class="flex-1 text-center border border-gray-300 text-gray-700 py-2 rounded-lg font-semibold text-sm hover:bg-gray-50">Cancel</a>
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
