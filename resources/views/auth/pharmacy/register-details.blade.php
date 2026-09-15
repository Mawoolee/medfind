﻿<x-guest-layout>
    {{-- Decorative faded street-map texture for the confirmed-location box (CSS only, no external images). --}}
    <style>
        .location-confirmed-map {
            background-color: #f0fdf4;
            background-image:
                linear-gradient(rgba(220, 252, 231, 0.7), rgba(220, 252, 231, 0.7)),
                url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='260'%20height='260'%20viewBox='0%200%20260%20260'%3E%3Crect%20width='260'%20height='260'%20fill='%23f0fdf4'/%3E%3Cg%20fill='%234ade80'%20fill-opacity='0.27'%3E%3Cpath%20d='M150%2018%20C172%2028%20182%2052%20176%2074%20C170%2094%20146%20102%20126%2092%20C110%2084%20106%2062%20118%2044%20C126%2032%20138%2022%20150%2018%20Z'/%3E%3Crect%20x='40'%20y='150'%20width='52'%20height='40'%20rx='10'/%3E%3C/g%3E%3Cg%20fill='%2386efac'%20fill-opacity='0.27'%3E%3Ccircle%20cx='202'%20cy='196'%20r='20'/%3E%3Cpath%20d='M28%2050%20L64%2044%20L72%2074%20L44%2088%20L20%2072%20Z'/%3E%3C/g%3E%3Cg%20fill='none'%20stroke='%23475569'%20stroke-opacity='0.35'%20stroke-linecap='round'%3E%3Cpath%20stroke-width='6.5'%20d='M-20%2074%20C40%2050%2080%20110%20140%2098%20C200%2086%20230%20130%20290%20112'/%3E%3Cpath%20stroke-width='6'%20d='M60%20-20%20C74%2050%2030%2090%2058%20150%20C82%20202%2050%20230%2074%20290'/%3E%3C/g%3E%3Cg%20fill='none'%20stroke='%2364748b'%20stroke-opacity='0.35'%20stroke-linecap='round'%3E%3Cpath%20stroke-width='3.5'%20d='M-20%20200%20C50%20186%2090%20226%20150%20208%20C210%20190%20240%20214%20290%20198'/%3E%3Cpath%20stroke-width='3'%20d='M186%20-20%20C176%2040%20214%2080%20200%20140%20C188%20192%20214%20226%20204%20290'/%3E%3Cpath%20stroke-width='2.5'%20d='M120%2098%20Q132%20140%20168%20150'/%3E%3Cpath%20stroke-width='2.5'%20d='M58%20120%20Q90%20132%2094%20176'/%3E%3Cpath%20stroke-width='2'%20d='M200%20120%20Q168%20128%20150%20160'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 260px 260px;
            background-repeat: repeat;
        }
    </style>

    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <!-- Logo -->
        <div class="auth-logo-container mb-4 text-center px-6 pt-0 pb-3 overflow-hidden rounded-xl border border-gray-200/50 dark:border-[rgba(0,220,255,0.45)] shadow-lg dark:shadow-[0_0_18px_rgba(0,200,255,0.15),0_8px_32px_rgba(0,0,0,0.5)]">
            <div class="flex items-center justify-center gap-0 overflow-hidden">
                <img src="{{ asset('images/MedFind Final Icon.png') }}" alt="MedFind Icon" class="h-16 sm:h-20 w-auto -mr-9">
                <img src="{{ asset('images/Final Logo MedFind.png') }}" alt="MedFind" class="h-36 sm:h-52 w-auto -my-10 sm:-my-16" style="filter: drop-shadow(0 0 0.5px white) drop-shadow(0 0 0.5px white);">
            </div>
            <p class="text-lg sm:text-base text-gray-700 dark:text-white/90 font-light mt-1">Pharmacy details</p>
            <p class="text-xs text-gray-500 dark:text-white/60 font-light mt-0.5">Pharmacy Owner &middot; Step 2 of 2</p>
        </div>

        <!-- Card -->
        <div class="auth-card-container auth-card w-full max-w-sm rounded-xl overflow-hidden backdrop-blur-xl border border-gray-200/50 dark:border-[rgba(0,220,255,0.45)] shadow-lg dark:shadow-[0_0_18px_rgba(0,200,255,0.15),0_8px_32px_rgba(0,0,0,0.5)]">
            <div class="p-6">
                <form method="POST" action="{{ route('register.pharmacy.store') }}">
                    @csrf

                    <!-- Pharmacy Name -->
                    <div class="mb-4">
                        <label for="pharmacy_name" class="block text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wider mb-1.5">
                            Pharmacy Name <span class="text-red-500">*</span>
                        </label>
                        <input id="pharmacy_name"
                               type="text"
                               name="pharmacy_name"
                               value="{{ old('pharmacy_name') }}"
                               required
                               autofocus
                               autocomplete="organization"
                               class="w-full px-4 py-3 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                               placeholder="e.g. City Pharmacy">
                        <x-input-error :messages="$errors->get('pharmacy_name')" class="mt-1" />
                    </div>

                    <!-- Address -->
                    <div class="mb-4">
                        <label for="pharmacyAddress" class="block text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wider mb-1.5">
                            Address <span class="text-red-500">*</span>
                        </label>
                        <input id="pharmacyAddress"
                               type="text"
                               name="pharmacyAddress"
                               value="{{ old('pharmacyAddress') }}"
                               required
                               class="w-full px-4 py-3 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                               placeholder="Full pharmacy address">
                        <x-input-error :messages="$errors->get('pharmacyAddress')" class="mt-1" />
                    </div>

                    <!-- Contact Number -->
                    <div class="mb-4">
                        <label for="contactNumber" class="block text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wider mb-1.5">
                            Contact Number
                        </label>
                        <input id="contactNumber"
                               type="text"
                               name="contactNumber"
                               value="{{ old('contactNumber') }}"
                               class="w-full px-4 py-3 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                               placeholder="e.g. 09XX-XXX-XXXX">
                        <x-input-error :messages="$errors->get('contactNumber')" class="mt-1" />
                    </div>

                    <!-- Pharmacy Location -->
                    @php
                        $lat = old('latitude', $location['latitude'] ?? null);
                        $lng = old('longitude', $location['longitude'] ?? null);
                        $addr = old('location_address', $location['address'] ?? null);
                        $hasLocation = $lat !== null && $lat !== '' && $lng !== null && $lng !== '';
                    @endphp
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wider mb-1.5">
                            Pharmacy Location <span class="text-red-500">*</span>
                        </label>

                        <!-- Confirmed state (shown when a location is set) -->
                        <div id="locationConfirmed" class="{{ $hasLocation ? '' : 'hidden' }} location-confirmed-map relative mb-2 overflow-hidden rounded-lg border border-green-300 px-3 py-2.5">
                            <a href="{{ route('register.pharmacy.location') }}"
                               aria-label="Change location"
                               title="Change location"
                               class="absolute top-2.5 right-3 z-20 inline-flex items-center justify-center text-[#9400D3] transition hover:text-[#191970]">
                                <i class="fas fa-pen text-sm"></i>
                            </a>
                            <div class="relative z-10">
                                <div class="flex items-center gap-1.5 pr-8 text-sm font-semibold text-green-700">
                                    <i class="fas fa-circle-check"></i>
                                    Location confirmed
                                </div>
                                @if ($addr)
                                    <p class="mt-1 pr-8 text-xs text-[#191970] leading-snug">{{ $addr }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Empty-state hint -->
                        <p id="locationHint" class="{{ $hasLocation ? 'hidden' : '' }} hidden text-xs text-gray-400 font-light mb-2">
                            No location set yet.
                        </p>

                        <!-- Full-width clickable card linking to the map page (empty state only) -->
                        @if (! $hasLocation)
                        <a href="{{ route('register.pharmacy.location') }}"
                           class="group flex w-full flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-gray-300 dark:border-white/20 bg-gray-50 dark:bg-white/5 px-4 py-6 text-center cursor-pointer transition hover:border-blue-500 dark:hover:border-white/40 hover:bg-blue-50/30 dark:hover:bg-white/10">
                            <i class="fas fa-map-location-dot text-2xl text-gray-500 dark:text-white/60"></i>
                            <span class="text-sm font-semibold text-gray-700 dark:text-white">Set Pharmacy Location</span>
                            <span class="text-xs font-light text-gray-500 dark:text-white/50">Search address or drop a pin on the map</span>
                        </a>
                        @endif

                        <!-- Coordinates submitted with the form as hidden inputs -->
                        <input type="hidden" name="latitude" id="latitude" value="{{ $hasLocation ? $lat : '' }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ $hasLocation ? $lng : '' }}">
                        <input type="hidden" name="location_address" id="location_address" value="{{ $addr }}">
                        <x-input-error :messages="$errors->get('latitude')" class="mt-1" />
                        <x-input-error :messages="$errors->get('longitude')" class="mt-1" />
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-[#191970] text-[#D9F855] text-base font-medium py-3 rounded-lg hover:bg-[#2a2a8a] transition">
                        Create account
                    </button>

                    <!-- Back Link -->
                    <p class="text-center text-sm text-gray-600 dark:text-white/90 font-normal mt-4">
                        Need to change your details?
                        <a href="{{ route('register.pharmacy') }}" class="text-blue-600 dark:text-white hover:text-blue-800 dark:hover:text-[#D9F855] transition font-bold">
                            Go back
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
