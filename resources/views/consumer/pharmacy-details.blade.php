@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f0f0ff] py-6 px-4 flex justify-center items-start overflow-y-auto font-sans">
    <div class="w-full max-w-lg bg-white rounded-[24px] p-5 shadow-xl border border-[#9400D3]/10 relative">

        <!-- Back Button -->
        <a href="{{ route('consumer.dashboard') }}" class="inline-flex items-center text-[#9400D3] hover:text-[#191970] text-xs font-semibold mb-3 transition">
            <i class="fas fa-arrow-left mr-1.5"></i> Back to Map
        </a>

        @if(isset($pharmacy))
            <!-- Pharmacy Header -->
            <div class="mb-3">
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="text-xl">🏪</span>
                    <h1 class="text-base font-extrabold text-[#191970] leading-tight">{{ $pharmacy->pharmacy_name }}</h1>
                </div>
                <p class="text-xs text-gray-500 flex items-center gap-1 ml-0.5 mb-1">
                    <span>📍</span> {{ $pharmacy->pharmacyAddress }}
                </p>
                @if($pharmacy->contactNumber)
                    <p class="text-xs text-[#9400D3] flex items-center gap-1 ml-0.5 mb-2">
                        <i class="fas fa-phone text-[10px]"></i> {{ $pharmacy->contactNumber }}
                    </p>
                @endif
                <div class="inline-flex items-center gap-1.5 bg-[#f8f4ff] text-[#191970] text-[11px] font-semibold px-3 py-1 rounded-full border border-[#9400D3]/10">
                    <span>📏</span> Distance calculated from your location
                </div>
            </div>

            <!-- Mini Map -->
            <div id="mini-map" class="w-full h-32 rounded-2xl mb-4 overflow-hidden border border-[#9400D3]/10 shadow-inner"></div>

            <!-- Products / Medicine List -->
            <div class="bg-[#f8f4ff] rounded-2xl p-3.5 mb-4 border border-[#9400D3]/10">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-1.5 text-[#191970] font-extrabold text-xs">
                        <span class="text-sm">💊</span> All Products
                    </div>
                    <span class="text-[10px] text-[#9400D3] font-semibold">Tap item for details</span>
                </div>

                @if($pharmacy->inventory && $pharmacy->inventory->count() > 0)
                    <div class="space-y-2">
                        @foreach($pharmacy->inventory as $item)
                            <div class="flex items-center justify-between gap-2 border-b border-[#9400D3]/10 pb-2 last:border-none last:pb-0
                                        cursor-pointer hover:bg-white/70 rounded-xl px-2 transition"
                                 onclick="showMedicineDetail(
                                     '{{ addslashes($item->medicine->medicine_name ?? 'Unknown') }}',
                                     '{{ addslashes($item->medicine->dosage ?? '') }}',
                                     '{{ addslashes($item->medicine->manufacturer ?? '') }}',
                                     '{{ addslashes($item->medicine->category ?? '') }}',
                                     {{ $item->medicine->requiresPrescription ? 'true' : 'false' }},
                                     {{ $item->price }},
                                     {{ $item->stockQuantity }}
                                 )">
                                <div>
                                    <p class="font-bold text-[#191970] text-xs leading-snug">
                                        {{ $item->medicine->medicine_name }}
                                        @if($item->medicine->dosage)
                                            <span class="font-normal text-gray-500">{{ $item->medicine->dosage }}</span>
                                        @endif
                                    </p>
                                    @if($item->medicine->manufacturer)
                                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $item->medicine->manufacturer }}</p>
                                    @endif
                                    @if($item->medicine->requiresPrescription)
                                        <p class="text-[10px] text-[#9400D3] font-semibold flex items-center gap-1 mt-0.5">
                                            <span>🔞</span> Prescription Required
                                        </p>
                                    @endif
                                </div>
                                <div class="shrink-0 flex flex-col items-end gap-1">
                                    <span class="bg-[#191970] text-[#D9F855] text-[11px] font-bold px-3 py-1 rounded-full inline-block shadow-sm">
                                        ₱{{ number_format($item->price, 0) }}
                                    </span>
                                    <span class="text-[10px] text-gray-400">{{ $item->stockQuantity }} pcs</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-400 text-xs text-center py-3 font-medium">No medicines currently available.</p>
                @endif
            </div>

            <!-- Contact for Inquiries -->
            <div id="contact" class="bg-white rounded-2xl p-4 mb-4 border border-[#9400D3]/15 shadow-sm">
                <div class="flex items-center gap-1.5 text-[#191970] font-extrabold text-xs mb-3">
                    <span class="text-sm">💬</span> Message Pharmacy
                </div>

                @auth
                    <form id="contactForm" onsubmit="submitInquiry(event, {{ $pharmacy->id }})">
                        @csrf
                        <textarea id="inquiryMessage" rows="3"
                            placeholder="Type your message or prescription inquiry..."
                            class="w-full border border-[#9400D3]/20 rounded-xl px-3 py-2 text-xs text-[#191970] outline-none focus:border-[#9400D3] resize-none mb-2 bg-[#f8f4ff]"></textarea>
                        <button type="submit"
                            class="w-full bg-[#191970] hover:bg-[#2a2a8a] text-[#D9F855] font-bold py-2.5 rounded-full text-xs transition active:scale-95 shadow-md">
                            📨 Send Message
                        </button>
                    </form>
                    <div id="contactSuccess" class="hidden text-green-600 text-xs font-semibold text-center mt-2">
                        ✅ Message sent successfully!
                    </div>
                @else
                    <p class="text-xs text-gray-500 text-center mb-2">Please log in to send inquiries.</p>
                    <a href="{{ route('login') }}" class="block w-full bg-[#191970] text-[#D9F855] font-bold py-2.5 rounded-full text-xs text-center">
                        Log in to Contact
                    </a>
                @endauth
            </div>

            <!-- Directions Button -->
            <a href="{{ route('consumer.dashboard') }}?dir=1&lat={{ $pharmacy->latitude }}&lng={{ $pharmacy->longitude }}"
               class="block w-full bg-[#9400D3] hover:bg-[#7a00b0] text-white font-bold py-2.5 rounded-full text-xs text-center shadow-md transition active:scale-95">
                🗺️ Get Directions on Map
            </a>

        @else
            <div class="text-center py-10">
                <i class="fas fa-store text-3xl text-gray-300 block mb-2"></i>
                <p class="text-gray-500 font-semibold text-xs">Pharmacy not found.</p>
            </div>
        @endif
    </div>
</div>

<!-- Medicine Detail Modal -->
<div id="medicineModal" class="fixed inset-0 bg-black/40 z-[99999] hidden items-center justify-center px-4"
     onclick="if(event.target===this) closeMedicineModal()">
    <div class="bg-white rounded-[24px] p-6 w-full max-w-sm shadow-2xl border border-[#9400D3]/10 relative">
        <button onclick="closeMedicineModal()"
            class="absolute top-4 right-4 text-gray-400 hover:text-[#9400D3] text-lg leading-none bg-transparent border-none cursor-pointer">✕</button>
        <div class="flex items-center gap-2 mb-4">
            <span class="text-2xl">💊</span>
            <h2 id="modalMedName" class="text-base font-extrabold text-[#191970]"></h2>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-xs">Brand / Name</span>
                <span id="modalBrand" class="text-[#191970] font-bold text-xs text-right"></span>
            </div>
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-xs">Dosage</span>
                <span id="modalDosage" class="text-[#191970] font-bold text-xs text-right"></span>
            </div>
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-xs">Manufacturer / Generic</span>
                <span id="modalManufacturer" class="text-[#191970] font-bold text-xs text-right"></span>
            </div>
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-xs">Category</span>
                <span id="modalCategory" class="text-[#191970] font-bold text-xs text-right"></span>
            </div>
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-xs">Price</span>
                <span id="modalPrice" class="text-[#191970] font-bold text-xs text-right"></span>
            </div>
            <div class="flex justify-between pb-2">
                <span class="text-gray-500 font-semibold text-xs">Stock</span>
                <span id="modalStock" class="text-[#191970] font-bold text-xs text-right"></span>
            </div>
            <div id="modalRxRow" class="hidden text-[10px] text-[#9400D3] font-semibold text-center pt-1">
                🔞 Prescription Required
            </div>
        </div>
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
            subdomains: 'abcd', maxZoom: 19
        }).addTo(miniMap);

        const miniIcon = L.divIcon({
            html: `<div style="width:14px;height:14px;border-radius:50%;background:#191970;border:2.5px solid #D9F855;box-shadow:0 2px 6px rgba(25,25,112,0.3);"></div>`,
            iconSize: [14,14], iconAnchor: [7,7]
        });
        L.marker([{{ $pharmacy->latitude }}, {{ $pharmacy->longitude }}], { icon: miniIcon }).addTo(miniMap);
    @endif

    // Scroll to contact section if #contact in URL
    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.hash === '#contact') {
            const el = document.getElementById('contact');
            if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 400);
        }
    });

    function showMedicineDetail(name, dosage, manufacturer, category, prescription, price, stock) {
        document.getElementById('modalMedName').textContent = name;
        document.getElementById('modalBrand').textContent = name || '—';
        document.getElementById('modalDosage').textContent = dosage || '—';
        document.getElementById('modalManufacturer').textContent = manufacturer || '—';
        document.getElementById('modalCategory').textContent = category || '—';
        document.getElementById('modalPrice').textContent = price ? '₱' + parseFloat(price).toLocaleString() : '—';
        document.getElementById('modalStock').textContent = stock + ' pcs';
        document.getElementById('modalRxRow').classList.toggle('hidden', !prescription);
        const modal = document.getElementById('medicineModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeMedicineModal() {
        const modal = document.getElementById('medicineModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function submitInquiry(e, pharmacyId) {
        e.preventDefault();
        const message = document.getElementById('inquiryMessage').value.trim();
        if (!message) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("consumer.message.send") }}';

        const csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);

        const phInput = document.createElement('input');
        phInput.type = 'hidden'; phInput.name = 'pharmacy_id';
        phInput.value = pharmacyId;
        form.appendChild(phInput);

        const msgInput = document.createElement('input');
        msgInput.type = 'hidden'; msgInput.name = 'message';
        msgInput.value = message;
        form.appendChild(msgInput);

        document.body.appendChild(form);
        form.submit();
    }
</script>
@endsection