@extends('layouts.app')

@section('title', 'MedFind — System Evaluation Survey')

@section('content')
<div class="container mx-auto px-4 py-10 max-w-3xl">

    {{-- Header --}}
    <div class="text-center mb-8">
        <p class="text-xs font-semibold uppercase tracking-widest text-[#9400D3] mb-1">ISO/IEC 25010 Software Quality Evaluation</p>
        <h1 class="text-3xl font-extrabold text-[#191970] mb-2">MedFind System Evaluation Survey</h1>
        <p class="text-gray-500 text-sm max-w-xl mx-auto">
            Please rate each statement from <strong>1 (Strongly Disagree)</strong> to <strong>5 (Strongly Agree)</strong>.
            Your feedback helps improve MedFind for the people of Legazpi City.
        </p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 text-sm">
            <p class="font-semibold mb-1">Please correct the following:</p>
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('survey.store') }}" class="space-y-8">
        @csrf

        {{-- Respondent Info --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4">About You</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">I am a… <span class="text-red-500">*</span></label>
                    <select name="respondent_type" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
                        <option value="">-- Select --</option>
                        <option value="consumer" {{ old('respondent_type') === 'consumer' ? 'selected' : '' }}>Consumer / Medicine Seeker</option>
                        <option value="pharmacy" {{ old('respondent_type') === 'pharmacy' ? 'selected' : '' }}>Pharmacy Operator / Staff</option>
                        <option value="admin"    {{ old('respondent_type') === 'admin'    ? 'selected' : '' }}>System Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-gray-400 text-xs">(optional)</span></label>
                    <input type="text" name="respondent_name" value="{{ old('respondent_name') }}"
                        placeholder="Your name or leave blank"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
                </div>
            </div>
        </div>

        {{-- Likert scale legend --}}
        <div class="flex items-center justify-end gap-2 text-xs text-gray-400 -mb-4">
            <span>1 = Strongly Disagree</span>
            <span class="mx-1">·</span>
            <span>5 = Strongly Agree</span>
        </div>

        @php
            $charColors = [
                'Functional Suitability' => ['border' => 'border-blue-400',  'bg' => 'bg-blue-50',  'badge' => 'bg-blue-100 text-blue-700',  'dot' => 'bg-blue-500'],
                'Usability'              => ['border' => 'border-[#9400D3]', 'bg' => 'bg-purple-50','badge' => 'bg-purple-100 text-purple-700','dot' => 'bg-[#9400D3]'],
                'Security'               => ['border' => 'border-green-400', 'bg' => 'bg-green-50', 'badge' => 'bg-green-100 text-green-700', 'dot' => 'bg-green-500'],
            ];
            $charDesc = [
                'Functional Suitability' => 'Does the system do what it is supposed to do?',
                'Usability'              => 'How easy and pleasant is the system to use?',
                'Security'               => 'How well does the system protect data and maintain trust?',
            ];
        @endphp

        @foreach($questions as $characteristic => $items)
            @php $colors = $charColors[$characteristic]; @endphp
            <div class="bg-white rounded-2xl shadow-sm border-l-4 {{ $colors['border'] }} border border-gray-100 overflow-hidden">
                <div class="{{ $colors['bg'] }} px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $colors['badge'] }}">ISO/IEC 25010</span>
                        <h2 class="text-base font-bold text-gray-800">{{ $characteristic }}</h2>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">{{ $charDesc[$characteristic] }}</p>
                </div>

                <div class="divide-y divide-gray-50">
                    @foreach($items as $field => $statement)
                        @php $old = old($field); @endphp
                        <div class="px-6 py-4">
                            <p class="text-sm text-gray-800 font-medium mb-3">{{ $statement }}</p>
                            <div class="flex items-center gap-2 flex-wrap">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="flex flex-col items-center gap-1 cursor-pointer group">
                                        <input type="radio" name="{{ $field }}" value="{{ $i }}"
                                            {{ $old == $i ? 'checked' : '' }} required
                                            class="sr-only peer">
                                        <span class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center text-sm font-bold
                                            text-gray-400 transition-all duration-150
                                            peer-checked:border-[#9400D3] peer-checked:bg-[#9400D3] peer-checked:text-white
                                            group-hover:border-[#9400D3]/60 group-hover:text-[#9400D3]">
                                            {{ $i }}
                                        </span>
                                        <span class="text-[10px] text-gray-400 leading-none">
                                            @if($i === 1) SD @elseif($i === 2) D @elseif($i === 3) N @elseif($i === 4) A @else SA @endif
                                        </span>
                                    </label>
                                @endfor
                                <span class="ml-3 text-xs text-gray-400 hidden md:block">
                                    SD = Strongly Disagree &nbsp;·&nbsp; A = Agree &nbsp;·&nbsp; SA = Strongly Agree
                                </span>
                            </div>
                            @error($field)
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Open-ended comments --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-bold text-gray-800 mb-3">Additional Comments <span class="text-gray-400 text-xs font-normal">(optional)</span></h2>
            <textarea name="comments" rows="4"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 resize-none"
                placeholder="Any suggestions, issues, or feedback about MedFind...">{{ old('comments') }}</textarea>
        </div>

        <div class="flex justify-center">
            <button type="submit"
                class="bg-[#191970] hover:bg-[#2a2a8a] text-[#D9F855] font-bold px-10 py-3 rounded-full text-sm transition shadow-lg">
                <i class="fas fa-paper-plane mr-2"></i>Submit Evaluation
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
// Highlight radio circles on click even before CSS peer works (Alpine not needed)
document.querySelectorAll('input[type=radio]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        // Deselect siblings
        document.querySelectorAll('input[name="' + this.name + '"]').forEach(function(r) {
            r.closest('label').querySelector('span').classList.remove('ring-2','ring-[#9400D3]');
        });
    });
});
</script>
@endpush
@endsection
