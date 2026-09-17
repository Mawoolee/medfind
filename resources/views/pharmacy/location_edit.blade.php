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
        <x-back-button :href="route('pharmacy.dashboard')" label="Back to Dashboard" />
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

@endsection
