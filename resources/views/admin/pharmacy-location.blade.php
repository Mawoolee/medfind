{{-- resources/views/admin/pharmacy-location.blade.php --}}

@extends('layouts.app')

@section('title', 'Set Pharmacy Location')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6 max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800">Set Pharmacy Location</h1>
        <a href="{{ route('admin.pharmacy.add') }}" class="text-[#9400D3] hover:text-[#7a00b0]">
            <i class="fas fa-arrow-left mr-2"></i>Back without saving
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-5 sm:p-6 max-w-2xl mx-auto">
        <form method="POST" action="{{ route('admin.pharmacy.location.store') }}">
            @csrf

            {{-- Search address --}}
            <div class="mb-4">
                <label for="addressSearch" class="block text-gray-700 text-sm font-medium mb-2">
                    Search Address
                </label>
                <div class="flex gap-2">
                    <input id="addressSearch"
                           type="text"
                           autocomplete="off"
                           class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Enter street address, city, or area...">
                    <button type="button"
                            id="addressSearchBtn"
                            class="px-4 py-2.5 rounded-lg text-base font-semibold text-white bg-blue-600 hover:bg-blue-700 transition">
                        <i class="fas fa-magnifying-glass"></i>
                    </button>
                </div>
                {{-- Search results list --}}
                <ul id="searchResults" class="hidden mt-2 border border-gray-200 rounded-lg divide-y divide-gray-100 overflow-hidden"></ul>
            </div>

            {{-- Use my current location --}}
            <button type="button"
                    id="useMyLocationBtn"
                    class="inline-flex items-center gap-1.5 mb-2 px-4 py-2 rounded-full text-sm font-semibold text-white bg-gray-700 hover:bg-gray-800 transition">
                <i class="fas fa-location-crosshairs"></i>
                Use my current location
            </button>

            {{-- Inline feedback notice --}}
            <p id="geoNotice" class="hidden text-xs mb-2"></p>

            {{-- Map --}}
            <div id="adminLocationMap"
                 class="w-full max-w-full h-[240px] sm:h-[320px] rounded-lg border border-gray-300 overflow-hidden"></div>
            <p class="text-xs text-gray-400 mt-1">
                <i class="fas fa-hand-pointer"></i> Click or drag the marker to refine.
                Coordinates are auto-generated from address and map placement.
            </p>

            {{-- Hidden fields submitted to the backend --}}
            <input type="hidden" name="latitude"  id="latitude"  value="{{ $location['latitude']  ?? '' }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ $location['longitude'] ?? '' }}">
            <input type="hidden" name="address"   id="address"   value="{{ $location['address']   ?? '' }}">

            @error('latitude')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('longitude')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror

            {{-- Save --}}
            <button type="submit"
                    id="saveLocationBtn"
                    class="w-full mt-5 bg-green-600 hover:bg-green-700 text-white text-base font-medium py-2.5 rounded-lg transition">
                <i class="fas fa-floppy-disk mr-2"></i>Save Location
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    if (typeof L === 'undefined') {
        document.write('<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"><\/script>');
    }
</script>
<script>
    (function () {
        // Philippines default centre (Manila).
        var DEFAULT_LAT = 14.5995;
        var DEFAULT_LNG = 120.9842;
        var NOMINATIM   = 'https://nominatim.openstreetmap.org';
        var APP_REF     = 'MedFind Admin Pharmacy Locator';

        function init() {
            if (typeof L === 'undefined') {
                return setTimeout(init, 100);
            }

            var mapEl = document.getElementById('adminLocationMap');
            if (!mapEl || mapEl.dataset.initialized) {
                return;
            }
            mapEl.dataset.initialized = 'true';

            var latInput    = document.getElementById('latitude');
            var lngInput    = document.getElementById('longitude');
            var addrInput   = document.getElementById('address');
            var notice      = document.getElementById('geoNotice');
            var geoBtn      = document.getElementById('useMyLocationBtn');
            var searchInput = document.getElementById('addressSearch');
            var searchBtn   = document.getElementById('addressSearchBtn');
            var resultsEl   = document.getElementById('searchResults');

            var hasExisting = latInput.value !== '' && lngInput.value !== '';
            var startLat    = hasExisting ? parseFloat(latInput.value)  : DEFAULT_LAT;
            var startLng    = hasExisting ? parseFloat(lngInput.value) : DEFAULT_LNG;

            var map = L.map(mapEl).setView([startLat, startLng], hasExisting ? 15 : 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            var marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

            function updateCoords(lat, lng, recenter, addressText) {
                var rLat = Math.round(lat * 1e6) / 1e6;
                var rLng = Math.round(lng * 1e6) / 1e6;
                latInput.value = rLat;
                lngInput.value = rLng;
                if (typeof addressText === 'string') {
                    addrInput.value = addressText;
                }
                marker.setLatLng([rLat, rLng]);
                if (recenter) {
                    map.setView([rLat, rLng], Math.max(map.getZoom(), 15));
                }
            }

            map.on('click', function (e) {
                updateCoords(e.latlng.lat, e.latlng.lng, false);
                reverseGeocode(e.latlng.lat, e.latlng.lng);
            });

            marker.on('dragend', function () {
                var pos = marker.getLatLng();
                updateCoords(pos.lat, pos.lng, false);
                reverseGeocode(pos.lat, pos.lng);
            });

            function showNotice(message, isError) {
                notice.textContent = message;
                notice.classList.remove('hidden', 'text-red-500', 'text-green-600');
                notice.classList.add(isError ? 'text-red-500' : 'text-green-600');
            }

            function runSearch() {
                var q = (searchInput.value || '').trim();
                if (q.length < 3) {
                    showNotice('Type at least 3 characters to search.', true);
                    return;
                }
                showNotice('Searching\u2026', false);
                var url = NOMINATIM + '/search?format=json&limit=5&addressdetails=1'
                    + '&q=' + encodeURIComponent(q)
                    + '&email=' + encodeURIComponent(APP_REF);

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { renderResults(data || []); })
                    .catch(function () {
                        showNotice('Address search is unavailable right now. Please set the pin manually.', true);
                    });
            }

            function renderResults(items) {
                resultsEl.innerHTML = '';
                if (!items.length) {
                    resultsEl.classList.add('hidden');
                    showNotice('No matches found. Try a different address or set the pin manually.', true);
                    return;
                }
                showNotice(items.length + ' result(s). Pick one or drag the pin.', false);
                items.forEach(function (item) {
                    var li = document.createElement('li');
                    li.className = 'px-3 py-2 text-xs text-gray-700 hover:bg-blue-50 cursor-pointer';
                    li.textContent = item.display_name;
                    li.addEventListener('click', function () {
                        updateCoords(parseFloat(item.lat), parseFloat(item.lon), true, item.display_name);
                        resultsEl.classList.add('hidden');
                        showNotice('Location set from search result.', false);
                    });
                    resultsEl.appendChild(li);
                });
                resultsEl.classList.remove('hidden');
            }

            function reverseGeocode(lat, lng) {
                var url = NOMINATIM + '/reverse?format=json'
                    + '&lat=' + encodeURIComponent(lat)
                    + '&lon=' + encodeURIComponent(lng)
                    + '&email=' + encodeURIComponent(APP_REF);
                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.display_name) {
                            addrInput.value = data.display_name;
                        }
                    })
                    .catch(function () { /* non-fatal */ });
            }

            if (searchBtn) {
                searchBtn.addEventListener('click', runSearch);
            }
            if (searchInput) {
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        runSearch();
                    }
                });
            }

            if (geoBtn) {
                geoBtn.addEventListener('click', function () {
                    if (!navigator.geolocation || !window.isSecureContext) {
                        showNotice('Location access needs a secure (HTTPS) connection. Please set the pin manually.', true);
                        return;
                    }
                    showNotice('Locating you\u2026', false);
                    navigator.geolocation.getCurrentPosition(
                        function (position) {
                            updateCoords(position.coords.latitude, position.coords.longitude, true);
                            reverseGeocode(position.coords.latitude, position.coords.longitude);
                            showNotice('Location set from your device.', false);
                        },
                        function (error) {
                            var msg = 'Could not get your location. Please set the pin manually.';
                            if (error && error.code === error.PERMISSION_DENIED) {
                                msg = 'Location permission denied. Please set the pin manually.';
                            }
                            showNotice(msg, true);
                        },
                        { enableHighAccuracy: true, timeout: 10000 }
                    );
                });
            }

            // Fix rendering when the container becomes visible.
            setTimeout(function () { map.invalidateSize(); }, 200);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
@endpush
