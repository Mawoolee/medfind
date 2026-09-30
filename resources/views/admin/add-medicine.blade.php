{{-- resources/views/admin/add-medicine.blade.php --}}

@extends('layouts.app')

@section('title', 'Add Medicine')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Add Medicine</h1>
        <a href="{{ route('admin.medicines') }}" class="text-[#9400D3] hover:text-[#7a00b0]">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-lg p-5 sm:p-6 max-w-2xl">
        <form action="{{ route('admin.medicine.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="medicine_name" class="block text-gray-700 text-sm font-medium mb-2">Medicine Name</label>
                <input type="text" id="medicine_name" name="medicine_name" value="{{ old('medicine_name') }}" required
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="dosage" class="block text-gray-700 text-sm font-medium mb-2">Dosage</label>
                <input type="text" id="dosage" name="dosage" value="{{ old('dosage') }}" placeholder="e.g. 500mg"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="manufacturer" class="block text-gray-700 text-sm font-medium mb-2">Manufacturer</label>
                <input type="text" id="manufacturer" name="manufacturer" value="{{ old('manufacturer') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            @php
                $selectedCategories = old('categories', old('category') ? [\App\Support\MedicineCategory::optionValue(old('category'))] : []);
                $selectedCategories = is_array($selectedCategories) ? $selectedCategories : [];
                $selectedCategoryLabels = collect($selectedCategories)->map(fn ($value) => $categoryOptions[$value] ?? $value)->all();
            @endphp
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-medium mb-2">Categories</label>
                <input type="hidden" name="categories_present" value="1">
                <details id="medicine-category-picker" class="relative">
                    <summary id="medicine-category-summary" class="flex cursor-pointer list-none items-center justify-between gap-3 w-full border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <span>{{ $selectedCategoryLabels ? implode(', ', $selectedCategoryLabels) : 'Select one or more categories' }}</span>
                        <i class="fas fa-chevron-down text-xs text-gray-500" aria-hidden="true"></i>
                    </summary>
                    <div class="absolute z-20 mt-1 w-full max-h-60 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
                        @foreach ($categoryOptions as $value => $label)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-purple-50">
                                <input type="checkbox" name="categories[]" value="{{ $value }}" class="medicine-category-option rounded border-gray-300 text-purple-600 focus:ring-purple-500" {{ in_array($value, $selectedCategories, true) ? 'checked' : '' }}>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>
                <p class="mt-1 text-xs text-gray-500">Select every category that applies. For example, Bioflu can be Analgesic, Antipyretic, and Antiallergics / Antihistamines.</p>
            </div>

            <div class="mb-4">
                <label class="flex items-center gap-2 text-gray-700 text-sm font-medium">
                    <input type="checkbox" name="requiresPrescription" value="1" class="w-4 h-4" {{ old('requiresPrescription') ? 'checked' : '' }}>
                    Requires Prescription (Rx)
                </label>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit" class="inline-flex items-center justify-center bg-purple-600 hover:bg-purple-700 text-white px-6 py-2.5 rounded-xl transition duration-200 w-full sm:w-auto">
                    <i class="fas fa-plus mr-2"></i>Add Medicine
                </button>
                <a href="{{ route('admin.medicines') }}" class="inline-flex items-center justify-center bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2.5 rounded-xl transition duration-200 w-full sm:w-auto">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var picker = document.getElementById('medicine-category-picker');
    var summary = document.getElementById('medicine-category-summary');
    var checkboxes = Array.from(document.querySelectorAll('.medicine-category-option'));
    if (!picker || !summary) return;

    function updateSummary() {
        var selected = checkboxes.filter(function (checkbox) { return checkbox.checked; })
            .map(function (checkbox) { return checkbox.parentElement.querySelector('span').textContent.trim(); });
        var text = selected.length ? selected.join(', ') : 'Select one or more categories';
        summary.querySelector('span').textContent = text;
    }

    checkboxes.forEach(function (checkbox) { checkbox.addEventListener('change', updateSummary); });
    updateSummary();
});
</script>
@endsection
