@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f0f0ff] py-6 px-4 flex justify-center items-start overflow-y-auto font-sans">
    <div class="w-full max-w-sm bg-white rounded-[24px] p-5 shadow-xl border border-[#9400D3]/10 relative">
        
        <!-- Back Button -->
        <a href="{{ route('consumer.dashboard') }}" class="inline-flex items-center text-[#9400D3] hover:text-[#191970] text-xs font-semibold mb-3 transition">
            <i class="fas fa-arrow-left mr-1.5"></i> Back
        </a>

        @if(isset($pharmacy))
            <!-- Pharmacy Header -->
            <div class="mb-3">
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="text-xl">🏪</span>
                    <h1 class="text-base font-extrabold text-[#191970] leading-tight">{{ $pharmacy->pharmacy_name }}</h1>
                </div>
                <p class="text-xs text-gray-500 flex items-center gap-1 ml-0.5 mb-2">
                    <span>📍</span> {{ $pharmacy->pharmacyAddress }}
                </p>
                
                <!-- Distance Badge -->
                <div class="inline-flex items-center gap-1.5 bg-[#f8f4ff] text-[#191970] text-[11px] font-semibold px-3 py-1 rounded-full border border-[#9400D3]/10">
                    <span>✏️</span> Distance: 1.2 km from your location
                </div>
            </div>

            <!-- Mini Map -->
            <div id="mini-map" class="w-full h-32 rounded-2xl mb-4 overflow-hidden border border-[#9400D3]/10 shadow-inner"></div>

            <!-- Inventory Box -->
            <div class="bg-[#f8f4ff] rounded-2xl p-3.5 mb-4 border border-[#9400D3]/10">
                <div class="flex items-center gap-1.5 text-[#191970] font-extrabold text-xs mb-2.5">
                    <span class="text-sm">💊</span> Available Stock:
                </div>

                @if($pharmacy->inventory && $pharmacy->inventory->count() > 0)
                    <div class="space-y-2">
                        @foreach($pharmacy->inventory as $item)
                            <div class="flex items-center justify-between gap-2 border-b border-[#9400D3]/10 pb-2 last:border-none last:pb-0">
                                <div>
                                    <p class="font-bold text-[#191970] text-xs leading-snug">
                                        {{ $item->medicine->medicine_name }} {{ $item->medicine->dosage }}
                                    </p>
                                    @if(isset($item->medicine->prescription_required) && $item->medicine->prescription_required)
                                        <p class="text-[10px] text-[#9400D3] font-semibold flex items-center gap-1 mt-0.5">
                                            <span>🔞</span> Prescription Required
                                        </p>
                                    @endif
                                </div>
                                <div class="shrink-0">
                                    <span class="bg-[#191970] text-[#D9F855] text-[11px] font-bold px-3 py-1 rounded-full inline-block shadow-sm">
                                        ₱{{ number_format($item->price, 0) }} | {{ $item->stockQuantity }} pcs
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-400 text-xs text-center py-3 font-medium">No medicines currently available.</p>
                @endif
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2">
                <button onclick="sendMessage({{ $pharmacy->id }})" 
                        class="flex-1 bg-[#191970] hover:bg-[#2a2a8a] text-[#D9F855] font-bold py-2.5 px-2 rounded-full text-[10px] flex items-center justify-center gap-1 shadow-md transition-all active:scale-95">
                    💬 Chat & Prescription
                </button>
                
<a href="{{ route('consumer.dashboard') }}?dir=1&lat={{ $pharmacy->latitude }}&lng={{ $pharmacy->longitude }}" 
                   class="flex-1 bg-[#9400D3] hover:bg-[#7a00b0] text-white font-bold py-2.5 px-2 rounded-full text-[10px] flex items-center justify-center gap-1 shadow-md transition-all active:scale-95">
                    🗺️ Directions
                </a>
            </div>

        @else
            <!-- Not Found State -->
            <div class="text-center py-10">
                <i class="fas fa-store text-3xl text-gray-300 block mb-2"></i>
                <p class="text-gray-500 font-semibold text-xs">Pharmacy not found.</p>
            </div>
        @endif

    </div>
</div>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    @if(isset($pharmacy))
        const miniMap = L.map('mini-map', {
            center: [{{ $pharmacy->latitude }}, {{ $pharmacy->longitude }}],
            zoom: 15,
            zoomControl: false,
            attributionControl: false
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(miniMap);

        const miniIcon = L.divIcon({
            html: `<div style="width:14px;height:14px;border-radius:50%;background:#191970;border:2.5px solid #D9F855;box-shadow:0 2px 6px rgba(25,25,112,0.3);"></div>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });

        L.marker([{{ $pharmacy->latitude }}, {{ $pharmacy->longitude }}], { icon: miniIcon }).addTo(miniMap);
    @endif

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