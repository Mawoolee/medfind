@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f0f0ff] py-6 px-4 font-sans">
    <div class="w-full max-w-7xl mx-auto px-0 sm:px-6">

        <!-- Back Button -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
                <a href="{{ route('consumer.dashboard') }}" class="inline-flex items-center text-[#9400D3] hover:text-[#191970] text-sm font-semibold transition">
                    <i class="fas fa-arrow-left mr-1.5"></i> Back to Map
                </a>
                <div class="ml-auto flex items-center gap-2">
                    @auth
                    <a href="{{ route('consumer.messages.chat', $pharmacy->id) }}"
                       class="inline-flex items-center justify-center gap-1.5 bg-[#191970] hover:bg-[#2a2a8a] text-[#D9F855] font-bold px-4 py-2 rounded-full text-sm transition">
                        <i class="fas fa-comments"></i> Message
                    </a>
                    @endauth
                    <a href="{{ route('consumer.dashboard') }}?dir=1&lat={{ $pharmacy->latitude }}&lng={{ $pharmacy->longitude }}"
                       class="inline-flex items-center justify-center gap-1.5 bg-[#9400D3] hover:bg-[#7a00b0] text-white font-bold px-4 py-2 rounded-full text-sm transition">
                        <i class="fas fa-directions"></i> Directions
                    </a>
                </div>
            </div>

        @if(isset($pharmacy))
            <!-- Pharmacy Header -->
            <div class="mb-3">
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="text-xl"></span>
                    <h1 class="text-xl sm:text-2xl font-extrabold text-[#191970] leading-tight">{{ $pharmacy->pharmacy_name }}</h1>
                </div>
                <p class="text-sm text-gray-500 flex items-center gap-1 ml-0.5 mb-1">
                    <span></span> {{ $pharmacy->pharmacyAddress }}
                </p>
                @if($pharmacy->contactNumber)
                    <p class="text-sm text-[#9400D3] flex items-center gap-1 ml-0.5 mb-2">
                        <i class="fas fa-phone text-base"></i> {{ $pharmacy->contactNumber }}
                    </p>
                @endif
                @if($pharmacy->operating_hours)
                    <p class="text-sm text-gray-500 flex items-center gap-1 ml-0.5 mb-2">
                        <i class="fas fa-clock text-base text-[#9400D3]"></i> {{ $pharmacy->operating_hours }}
                    </p>
                @endif
                <div class="inline-flex items-center gap-1.5 bg-[#f8f4ff] text-[#191970] text-sm font-semibold px-3 py-1 rounded-full border border-[#9400D3]/10">
                    <span></span> Distance calculated from your location
                </div>
            </div>

            <!-- Mini Map -->
            <div id="mini-map" class="w-full h-40 sm:h-32 rounded-2xl mb-4 overflow-hidden border border-[#9400D3]/10 shadow-inner"></div>

            <!-- Products / Medicine List -->
            <div class="bg-[#f8f4ff] rounded-2xl p-3.5 mb-4 border border-[#9400D3]/10">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-1.5 text-[#191970] font-extrabold text-sm">
                        <span class="text-base"></span> All Products
                    </div>
                    <span class="text-xs text-[#9400D3] font-semibold">Tap item for details</span>
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
                                     {{ (float) $item->representative_price }},
                                     {{ (int) $item->available_stock }}
                                 )">
                                <div>
                                    <p class="font-bold text-[#191970] text-base leading-snug">
                                        {{ $item->medicine->medicine_name }}
                                        @if($item->medicine->dosage)
                                            <span class="font-normal text-gray-500">{{ $item->medicine->dosage }}</span>
                                        @endif
                                    </p>
                                    @if($item->medicine->manufacturer)
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $item->medicine->manufacturer }}</p>
                                    @endif
                                    @if($item->medicine->requiresPrescription)
                                        <p class="text-xs text-[#9400D3] font-semibold flex items-center gap-1 mt-0.5">
                                            <span></span> Prescription Required
                                        </p>
                                    @endif
                                </div>
                                <div class="shrink-0 flex flex-col items-end gap-1">
                                    <span class="bg-[#191970] text-[#D9F855] text-sm font-bold px-3 py-1 rounded-full inline-block shadow-sm">
                                        ₱{{ number_format($item->representative_price, 0) }}
                                    </span>
                                    <span class="text-xs text-gray-400">{{ $item->available_stock }} pcs</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-400 text-sm text-center py-3 font-medium">No medicines currently available.</p>
                @endif
            </div>

            

        @else
            <div class="text-center py-10">
                <i class="fas fa-store text-3xl text-gray-300 block mb-2"></i>
                <p class="text-gray-500 font-semibold text-base">Pharmacy not found.</p>
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
            <span class="text-2xl"></span>
            <h2 id="modalMedName" class="text-base font-extrabold text-[#191970]"></h2>
        </div>
        <div class="space-y-2 text-base">
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-base">Brand / Name</span>
                <span id="modalBrand" class="text-[#191970] font-bold text-base text-right"></span>
            </div>
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-base">Dosage</span>
                <span id="modalDosage" class="text-[#191970] font-bold text-base text-right"></span>
            </div>
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-base">Manufacturer / Generic</span>
                <span id="modalManufacturer" class="text-[#191970] font-bold text-base text-right"></span>
            </div>
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-base">Category</span>
                <span id="modalCategory" class="text-[#191970] font-bold text-base text-right"></span>
            </div>
            <div class="flex justify-between border-b border-[#9400D3]/10 pb-2">
                <span class="text-gray-500 font-semibold text-base">Price</span>
                <span id="modalPrice" class="text-[#191970] font-bold text-base text-right"></span>
            </div>
            <div class="flex justify-between pb-2">
                <span class="text-gray-500 font-semibold text-base">Stock</span>
                <span id="modalStock" class="text-[#191970] font-bold text-base text-right"></span>
            </div>
            <div id="modalRxRow" class="hidden text-base text-[#9400D3] font-semibold text-center pt-1">
                 Prescription Required
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

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
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

    function updatePrescriptionLabel(input) {
        const label = document.getElementById('rxFileName');
        const clearBtn = document.getElementById('rxClearBtn');
        const preview = document.getElementById('rxPreview');
        const previewImg = document.getElementById('rxPreviewImg');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            label.textContent = file.name;
            label.style.color = '#191970';
            label.style.fontWeight = '600';
            clearBtn.classList.remove('hidden');

            // Show image preview if it is an image
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                // PDF — show a file icon placeholder instead
                preview.classList.remove('hidden');
                previewImg.src = '';
                previewImg.style.display = 'none';
                preview.innerHTML = '<div class="flex items-center gap-2 px-3 py-2 bg-white rounded-xl border border-[#9400D3]/15"><i class="fas fa-file-pdf text-red-500 text-lg"></i><span class="text-sm font-semibold text-[#191970]">' + file.name + '</span></div>';
            }
        }
    }

    function clearPrescription() {
        const input = document.getElementById('prescriptionFile');
        const label = document.getElementById('rxFileName');
        const clearBtn = document.getElementById('rxClearBtn');
        const preview = document.getElementById('rxPreview');
        input.value = '';
        label.textContent = 'No file chosen';
        label.style.color = '';
        label.style.fontWeight = '';
        clearBtn.classList.add('hidden');
        preview.classList.add('hidden');
    }
</script>
@endsection