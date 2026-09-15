﻿<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-8">
        <!-- Logo -->
        <div class="auth-logo-container mb-4 text-center px-6 pt-0 pb-3 overflow-hidden rounded-xl backdrop-blur-xl border border-gray-200/50 dark:border-[rgba(0,220,255,0.45)] shadow-lg dark:shadow-[0_0_18px_rgba(0,200,255,0.15),0_8px_32px_rgba(0,0,0,0.5)]" style="background-color: rgba(255,255,255,0.45); backdrop-filter: blur(12px);">
            <div class="flex items-center justify-center gap-0 overflow-hidden">
                <img src="{{ asset('images/MedFind Final Icon.png') }}" alt="MedFind Icon" class="h-16 sm:h-20 w-auto -mr-9">
                <img src="{{ asset('images/Final Logo MedFind.png') }}" alt="MedFind" class="h-36 sm:h-52 w-auto -my-10 sm:-my-16" style="filter: drop-shadow(0 0 0.5px white) drop-shadow(0 0 0.5px white);">
            </div>
            <p class="text-lg sm:text-base text-gray-700 dark:text-white/90 font-light mt-1">Pharmacy Location</p>
            <p class="text-xs text-gray-500 dark:text-white/60 font-light mt-0.5">Pharmacy Owner &middot; Step 2 of 2</p>
        </div>

        <!-- Card -->
        <div class="auth-card-container auth-card w-full max-w-md rounded-xl overflow-hidden backdrop-blur-xl border border-gray-200/50 dark:border-[rgba(0,220,255,0.45)] shadow-lg dark:shadow-[0_0_18px_rgba(0,200,255,0.15),0_8px_32px_rgba(0,0,0,0.5)]">
            <div class="p-6">
                <form method="POST" action="{{ route('register.pharmacy.location.store') }}">
                    @csrf

                    <!-- Search address -->
                    <div class="mb-4">
                        <label for="addressSearch" class="block text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wider mb-1.5">
                            Search Address
                        </label>
                        <div class="flex gap-2">
                            <input id="addressSearch"
                                   type="text"
                                   autocomplete="off"
                                   class="flex-1 px-4 py-3 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                                   placeholder="Enter street address, city, or area...">
                            <button type="button"
                                    id="addressSearchBtn"
                                    class="px-4 py-3 rounded-lg text-base font-semibold text-white bg-[#191970] hover:bg-[#2a2a8a] transition">
                                <i class="fas fa-magnifying-glass"></i>
                            </button>
                        </div>
                        <!-- Search results list -->
                        <ul id="searchResults" class="hidden mt-2 border border-gray-200 dark:border-white/20 rounded-lg divide-y divide-gray-100 dark:divide-white/10 overflow-hidden"></ul>
                    </div>

                    <!-- Use my current location -->
                    <button type="button"
                            id="useMyLocationBtn"
                            class="inline-flex items-center gap-1.5 mb-2 px-4 py-2 rounded-full text-sm font-semibold text-white bg-[#191970] hover:bg-[#2a2a8a] transition">
                        <i class="fas fa-location-crosshairs"></i>
                        Use my current location
                    </button>

                    <!-- Inline notice for geolocation / search feedback -->
                    <p id="geoNotice" class="hidden text-xs mb-2"></p>

                    <!-- Map -->
                    <div id="registerLocationMap"
                         class="w-full max-w-full h-[240px] sm:h-[300px] rounded-lg border border-gray-200 dark:border-white/20 overflow-hidden"></div>
                    <p class="text-xs text-gray-500 dark:text-white/50 font-light mt-1">
                        <i class="fas fa-hand-pointer"></i> Drag to refine.
                        Coordinates auto-generated from address and map placement.
                    </p>

                    <!-- Hidden fields submitted to the backend -->
                    <input type="hidden" name="latitude" id="latitude" value="{{ $location['latitude'] ?? '' }}">
                    <input type="hidden" name="longitude" id="longitude" value="{{ $location['longitude'] ?? '' }}">
                    <input type="hidden" name="address" id="address" value="{{ $location['address'] ?? '' }}">
                    <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                    <x-input-error :messages="$errors->get('longitude')" class="mt-2" />

                    <!-- Save -->
                    <button type="submit"
                            id="saveLocationBtn"
                            class="w-full mt-5 bg-[#191970] text-[#D9F855] text-base font-medium py-3 rounded-lg hover:bg-[#2a2a8a] transition">
                        Save Location
                    </button>

                    <!-- Back / cancel -->
                    <p class="text-center text-sm text-gray-600 dark:text-white/90 font-normal mt-4">
                        <a href="{{ route('register.pharmacy.details') }}" class="text-blue-600 dark:text-white hover:text-blue-800 dark:hover:text-[#D9F855] transition font-bold">
                            Back without saving
                        </a>
                    </p>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <p class="auth-footer text-xs text-gray-700 dark:text-white/70 font-light mt-8 px-4 py-2 rounded-lg border border-white/30 dark:border-[rgba(0,220,255,0.3)]">
            &copy; 2026 MedFind. All rights reserved.
        </p>
    </div>

    {{-- Leaflet (guarded against double-loading) --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script>
        if (typeof L === 'undefined') {
            document.write('<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"><\/script>');
        }
    </script>
    <script>
        (function () {
            var DEFAULT_LAT = 14.5995;
            var DEFAULT_LNG = 120.9842;
            var NOMINATIM = 'https://nominatim.openstreetmap.org';
            var APP_REF = 'MedFind Pharmacy Locator';

            function init() {
                if (typeof L === 'undefined') {
                    return setTimeout(init, 100);
                }
                var mapEl = document.getElementById('registerLocationMap');
                if (! mapEl || mapEl.dataset.initialized) {
                    return;
                }
                mapEl.dataset.initialized = 'true';

                var latInput = document.getElementById('latitude');
                var lngInput = document.getElementById('longitude');
                var addrInput = document.getElementById('address');
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
                    if (! items.length) {
                        resultsEl.classList.add('hidden');
                        showNotice('No matches found. Try a different address or set the pin manually.', true);
                        return;
                    }
                    showNotice(items.length + ' result(s). Pick one or drag the pin.', false);
                    items.forEach(function (item) {
                        var li = document.createElement('li');
                        li.className = 'px-3 py-2 text-xs text-gray-700 dark:text-white hover:bg-gray-100 dark:hover:bg-white/10 cursor-pointer';
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

                setTimeout(function () { map.invalidateSize(); }, 200);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
</x-guest-layout>
