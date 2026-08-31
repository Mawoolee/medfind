@extends('layouts.app')

@section('title', 'Set Pharmacy Location')

@section('content')
<div class="min-h-screen" style="background:#f0f0ff;">
<div class="container mx-auto px-4 py-10 max-w-2xl">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background:#191970;">
                <i class="fas fa-map-location-dot text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold" style="color:#191970;">Pharmacy Location</h1>
                <p class="text-sm text-gray-500">Search an address or drag the pin to set your location</p>
            </div>
        </div>
        <x-back-button :href="route('pharmacy.profile.edit')" label="Back to Profile" />
    </div>

    <form method="POST" action="{{ route('pharmacy.profile.location.store') }}">
        @csrf

        <div class="bg-white rounded-[20px] shadow-sm p-6 mb-6 border border-gray-100">

            {{-- Search address --}}
            <label for="addressSearch" class="block text-sm font-medium mb-1" style="color:#191970;">
                Search Address
            </label>
            <div class="flex gap-2 mb-3">
                <input id="addressSearch" type="text" autocomplete="off"
                    class="flex-1 border border-gray-200 rounded-xl px-3 py-2.5 text-base focus:outline-none focus:ring-2 transition-shadow"
                    placeholder="Enter street address, city, or area...">
                <button type="button" id="addressSearchBtn"
                    class="px-4 py-2.5 rounded-xl text-base font-semibold text-white transition-opacity hover:opacity-90"
                    style="background:#9400D3;">
                    <i class="fas fa-magnifying-glass"></i>
                </button>
            </div>
            <ul id="searchResults" class="hidden mb-3 border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden"></ul>

            {{-- Use my current location --}}
            <button type="button" id="useMyLocationBtn"
                class="inline-flex items-center gap-2 mb-3 px-4 py-2.5 rounded-full text-sm font-semibold text-white transition-opacity hover:opacity-90"
                style="background:#191970;">
                <i class="fas fa-location-crosshairs"></i>
                Use my current location
            </button>

            {{-- Inline notice --}}
            <p id="geoNotice" class="hidden text-xs mb-3"></p>

            {{-- Map --}}
            <div id="profileLocationMap"
                class="w-full max-w-full h-[260px] sm:h-[320px] rounded-xl border border-gray-200 overflow-hidden"></div>
            <p class="text-xs text-gray-400 mt-2">
                <i class="fas fa-hand-pointer"></i> Drag to refine.
                Coordinates auto-generated from address and map placement.
            </p>

            {{-- Location confirmed (read-only coordinates) --}}
            <p class="block text-sm font-medium mt-4 mb-2" style="color:#191970;">Location Confirmed</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="latDisplay" class="block text-sm font-medium mb-1" style="color:#191970;">Latitude</label>
                    <input id="latDisplay" type="text" readonly placeholder="Not set"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-base bg-gray-50 cursor-default focus:outline-none">
                </div>
                <div>
                    <label for="lngDisplay" class="block text-sm font-medium mb-1" style="color:#191970;">Longitude</label>
                    <input id="lngDisplay" type="text" readonly placeholder="Not set"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-base bg-gray-50 cursor-default focus:outline-none">
                </div>
            </div>

            {{-- Hidden fields submitted to the backend --}}
            <input type="hidden" name="latitude" id="latitude" value="{{ $location['latitude'] ?? '' }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ $location['longitude'] ?? '' }}">
            <input type="hidden" name="address" id="address" value="{{ $location['address'] ?? '' }}">

            @error('latitude')
                <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
            @enderror
            @error('longitude')
                <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
            @enderror

        </div>

        {{-- Save button --}}
        <button type="submit"
            class="w-full py-3 rounded-full font-bold text-sm transition-opacity hover:opacity-90 flex items-center justify-center gap-2"
            style="background:#191970;color:#D9F855;">
            <i class="fas fa-map-pin"></i>
            Save Location
        </button>

        <div class="mt-4 text-center">
            <a href="{{ route('pharmacy.profile.edit') }}" class="text-sm hover:underline" style="color:#9400D3;">
                <i class="fas fa-arrow-left mr-1"></i> Back without saving
            </a>
        </div>
    </form>

</div>
</div>

{{-- Leaflet CSS (guarded against double-loading) --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

{{-- Leaflet JS (guarded) --}}
<script>
    if (typeof L === 'undefined') {
        document.write('<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"><\/script>');
    }
</script>

<script>
    (function () {
        // Philippines default center (Manila).
        var DEFAULT_LAT = 14.5995;
        var DEFAULT_LNG = 120.9842;
        var NOMINATIM = 'https://nominatim.openstreetmap.org';
        var APP_REF = 'MedFind Pharmacy Locator';

        function init() {
            if (typeof L === 'undefined') {
                return setTimeout(init, 100);
            }

            var mapEl = document.getElementById('profileLocationMap');
            if (! mapEl || mapEl.dataset.initialized) {
                return;
            }
            mapEl.dataset.initialized = 'true';

            var latInput = document.getElementById('latitude');
            var lngInput = document.getElementById('longitude');
            var addrInput = document.getElementById('address');
            var latDisplay = document.getElementById('latDisplay');
            var lngDisplay = document.getElementById('lngDisplay');
            var notice = document.getElementById('geoNotice');
            var geoBtn = document.getElementById('useMyLocationBtn');
            var searchInput = document.getElementById('addressSearch');
            var searchBtn = document.getElementById('addressSearchBtn');
            var resultsEl = document.getElementById('searchResults');

            var hasExisting = latInput.value !== '' && lngInput.value !== '';
            var startLat = hasExisting ? parseFloat(latInput.value) : DEFAULT_LAT;
            var startLng = hasExisting ? parseFloat(lngInput.value) : DEFAULT_LNG;

            var map = L.map(mapEl).setView([startLat, startLng], hasExisting ? 15 : 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            var marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

            function refreshDisplays() {
                latDisplay.value = latInput.value;
                lngDisplay.value = lngInput.value;
            }
            if (hasExisting) {
                refreshDisplays();
            }

            function updateCoords(lat, lng, recenter, addressText) {
                var rLat = Math.round(lat * 1e6) / 1e6;
                var rLng = Math.round(lng * 1e6) / 1e6;
                latInput.value = rLat;
                lngInput.value = rLng;
                if (typeof addressText === 'string') {
                    addrInput.value = addressText;
                }
                marker.setLatLng([rLat, rLng]);
                refreshDisplays();
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

            // ---- Nominatim address search (only fires on user action) ----
            function runSearch() {
                var q = (searchInput.value || '').trim();
                if (q.length < 3) {
                    showNotice('Type at least 3 characters to search.', true);
                    return;
                }
                showNotice('Searching…', false);
                var url = NOMINATIM + '/search?format=json&limit=5&addressdetails=1'
                    + '&q=' + encodeURIComponent(q)
                    + '&email=' + encodeURIComponent(APP_REF);

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        renderResults(data || []);
                    })
                    .catch(function () {
                        showNotice('Address search is unavailable right now. Please set the pin manually.', true);
                    });
            }

            function renderResults(items) {
                resultsEl.innerHTML = '';
                if (! items.length) {
                    resultsEl.classList.add('hidden');
                    showNotice('No matches found. Try a different address or set the pin manually.', true);
                    return;
                }
                showNotice(items.length + ' result(s). Pick one or drag the pin.', false);
                items.forEach(function (item) {
                    var li = document.createElement('li');
                    li.className = 'px-3 py-2 text-xs text-[#191970] hover:bg-[#f0ebff] cursor-pointer';
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
                    if (! navigator.geolocation || ! window.isSecureContext) {
                        showNotice('Location access needs a secure (HTTPS) connection. Please set the pin manually.', true);
                        return;
                    }
                    showNotice('Locating you…', false);
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

            setTimeout(function () { map.invalidateSize(); }, 200);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
@endsection
