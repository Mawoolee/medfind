﻿<x-guest-layout :google-maps="true">
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

                <!-- Flash messages -->
                @if(session('success'))
                    <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-green-50 dark:bg-green-500/10 border border-green-300 dark:border-green-400/40 text-green-800 dark:text-green-200 flex items-center gap-2">
                        <i class="fas fa-check-circle text-green-500"></i>
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-red-50 dark:bg-red-500/10 border border-red-300 dark:border-red-400/40 text-red-800 dark:text-red-200 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                        {{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('register.pharmacy.location.store') }}">
                    @csrf

                    <!-- Validation feedback for the submitted location -->
                    @if($errors->hasAny(['latitude', 'longitude', 'address']))
                        <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-red-50 dark:bg-red-500/10 border border-red-300 dark:border-red-400/40 text-red-800 dark:text-red-200">
                            <p class="flex items-center gap-2 font-semibold">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                                We could not save this location.
                            </p>
                            <ul class="mt-1 ml-6 list-disc">
                                @foreach($errors->get('latitude') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                                @foreach($errors->get('longitude') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                                @foreach($errors->get('address') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

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

                    <!-- Map (initialized by the shared Google Maps location editor) -->
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
</x-guest-layout>
