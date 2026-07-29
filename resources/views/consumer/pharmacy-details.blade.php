{{-- resources/views/consumer/pharmacy-details.blade.php --}}

@extends('layouts.app')

@section('title', 'Pharmacy Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Pharmacy Details</h1>
        <a href="{{ route('consumer.search') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Search
        </a>
    </div>

    @if(isset($pharmacy))
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ $pharmacy->pharmacy_name }}</h2>
                <p class="text-gray-600 mb-4">{{ $pharmacy->pharmacyAddress }}</p>
                <p class="text-gray-600 mb-4"><i class="fas fa-phone mr-2"></i>{{ $pharmacy->contactNumber }}</p>

                <!-- Map placeholder -->
                <div class="bg-gray-200 rounded-lg h-64 mb-6 flex items-center justify-center">
                    <p class="text-gray-500"><i class="fas fa-map-marked-alt text-4xl mb-2 block"></i>Map View</p>
                </div>

                <h3 class="text-xl font-semibold text-gray-800 mb-4">Available Medicines</h3>
                @if($pharmacy->inventory && $pharmacy->inventory->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left text-gray-600">Medicine</th>
                                    <th class="px-4 py-2 text-left text-gray-600">Dosage</th>
                                    <th class="px-4 py-2 text-left text-gray-600">Price</th>
                                    <th class="px-4 py-2 text-left text-gray-600">Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pharmacy->inventory as $item)
                                    <tr class="border-t border-gray-200">
                                        <td class="px-4 py-2">{{ $item->medicine->medicine_name }}</td>
                                        <td class="px-4 py-2">{{ $item->medicine->dosage }}</td>
                                        <td class="px-4 py-2">₱{{ number_format($item->price, 2) }}</td>
                                        <td class="px-4 py-2">
                                            <span class="{{ $item->stockQuantity > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $item->stockQuantity > 0 ? $item->stockQuantity . ' available' : 'Out of Stock' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500">No medicines currently available at this pharmacy.</p>
                @endif

                <div class="mt-6">
                    <button onclick="sendMessage({{ $pharmacy->id }})" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg transition duration-200">
                        <i class="fas fa-comment mr-2"></i>Send Message
                    </button>
                    <a href="https://www.google.com/maps/search/?api=1&query={{ $pharmacy->latitude }},{{ $pharmacy->longitude }}" 
                       target="_blank"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition duration-200 ml-2">
                        <i class="fas fa-directions mr-2"></i>Get Directions
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg">
            <p class="text-yellow-800">Pharmacy not found.</p>
        </div>
    @endif
</div>

<script>
function sendMessage(pharmacyId) {
    const message = prompt('Type your message or prescription inquiry:');
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