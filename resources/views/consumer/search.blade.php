@extends('layouts.app')

@section('content')
<div class="h-full bg-[#f0f0ff] overflow-y-auto p-4">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold text-[#191970]">Search Results</h1>
            <a href="{{ route('consumer.dashboard') }}" class="text-[#9400D3] hover:text-[#191970] text-sm font-medium transition">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>

        @if(isset($query))
            <p class="text-sm text-gray-500 mb-4">Results for: <span class="font-semibold text-[#9400D3]">"{{ $query }}"</span></p>
        @endif

        @if(isset($results) && $results->count() > 0)
            <div class="space-y-3">
                @foreach($results as $item)
                    <div class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition border border-[#9400D3]/10">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-semibold text-[#191970]">{{ $item->medicine->medicine_name }}</h3>
                                    <span class="text-xs bg-[#191970] text-[#D9F855] px-2 py-0.5 rounded-full">
                                        {{ $item->stockQuantity > 0 ? 'In Stock' : 'Out of Stock' }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500">{{ $item->medicine->dosage }} • {{ $item->medicine->manufacturer }}</p>
                                <p class="text-sm font-semibold text-[#191970] mt-1">₱{{ number_format($item->price, 2) }}</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <i class="fas fa-store text-[#9400D3] text-xs"></i>
                                    <span class="text-sm text-gray-600">{{ $item->pharmacy->pharmacy_name }}</span>
                                    <span class="text-xs text-gray-400">• {{ $item->pharmacy->pharmacyAddress }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2 ml-4">
                                <a href="{{ route('consumer.pharmacy.details', $item->pharmacy->id) }}" 
                                   class="text-xs bg-[#191970] text-[#D9F855] px-3 py-1.5 rounded-lg hover:bg-[#2a2a8a] transition">
                                    View
                                </a>
                                <button onclick="sendMessage({{ $item->pharmacy->id }}, '{{ $item->medicine->medicine_name }}')" 
                                        class="text-xs bg-[#9400D3] text-white px-3 py-1.5 rounded-lg hover:bg-[#7a00b0] transition">
                                    <i class="fas fa-comment"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $results->links() }}
            </div>
        @else
            <div class="bg-white rounded-xl p-8 text-center border border-[#9400D3]/10">
                <i class="fas fa-search text-[#9400D3]/30 text-4xl block mb-3"></i>
                <p class="text-gray-500">No results found for your search.</p>
            </div>
        @endif
    </div>
</div>

<script>
function sendMessage(pharmacyId, medicineName) {
    const message = prompt(`Ask about "${medicineName}":`);
    if (message && message.trim()) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("consumer.message.send") }}';
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);
        
        const pharmacyInput = document.createElement('input');
        pharmacyInput.type = 'hidden';
        pharmacyInput.name = 'pharmacy_id';
        pharmacyInput.value = pharmacyId;
        form.appendChild(pharmacyInput);
        
        const messageInput = document.createElement('input');
        messageInput.type = 'hidden';
        messageInput.name = 'message';
        messageInput.value = message;
        form.appendChild(messageInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endsection