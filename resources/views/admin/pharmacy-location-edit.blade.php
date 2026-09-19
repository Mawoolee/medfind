{{-- resources/views/admin/pharmacy-location-edit.blade.php --}}

@extends('layouts.app')

@section('title', 'Edit Pharmacy Location')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6 max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800">
            Edit Location &mdash; {{ $pharmacy->pharmacy_name }}
        </h1>
        <a href="{{ route('admin.pharmacy.edit', $pharmacy->id) }}" class="text-[#9400D3] hover:text-[#7a00b0]">
            <i class="fas fa-arrow-left mr-2"></i>Back without saving
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="max-w-2xl mx-auto mb-5 bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-xl flex items-center gap-2 text-sm">
            <i class="fas fa-check-circle text-green-500"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="max-w-2xl mx-auto mb-5 bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-xl flex items-center gap-2 text-sm">
            <i class="fas fa-exclamation-circle text-red-500"></i>
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-lg p-5 sm:p-6 max-w-2xl mx-auto">
        <form method="POST" action="{{ route('admin.pharmacy.location.edit.store', $pharmacy->id) }}">
            @csrf

            {{-- Validation feedback for the submitted location --}}
            @if($errors->hasAny(['latitude', 'longitude', 'address']))
                <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-xl mb-4 text-sm">
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

            {{-- Search address --}}
            <div class="mb-4">
                <label for="addressSearch" class="block text-gray-700 text-sm font-medium mb-2">
                    Search Address
                </label>
                <div class="flex gap-2">
                    <input id="addressSearch"
                           type="text"
                           autocomplete="off"
                           class="flex-1 border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                           placeholder="Enter street address, city, or area...">
                    <button type="button"
                            id="addressSearchBtn"
                            class="px-4 py-2.5 rounded-xl text-base font-semibold text-white bg-[#9400D3] hover:bg-[#7a00b0] transition">
                        <i class="fas fa-magnifying-glass"></i>
                    </button>
                </div>
            </div>

            {{-- Use my current location --}}
            <button type="button"
                    id="useMyLocationBtn"
                    class="inline-flex items-center gap-1.5 mb-2 px-4 py-2 rounded-full text-sm font-semibold text-white bg-[#191970] hover:opacity-90 transition">
                <i class="fas fa-location-crosshairs"></i>
                Use my current location
            </button>

            {{-- Inline feedback notice --}}
            <p id="geoNotice" class="hidden text-xs mb-2"></p>

            {{-- Map (initialized by the shared Google Maps location editor) --}}
            <div id="adminLocationMap"
                 class="w-full max-w-full h-[240px] sm:h-[320px] rounded-xl border border-gray-300 overflow-hidden"></div>
            <p class="text-xs text-gray-400 mt-1">
                <i class="fas fa-hand-pointer"></i> Click or drag the marker to refine.
                Coordinates are auto-generated from address and map placement.
            </p>

            {{-- Hidden fields submitted to the backend --}}
            <input type="hidden" name="latitude"  id="latitude"  value="{{ $location['latitude']  ?? '' }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ $location['longitude'] ?? '' }}">
            <input type="hidden" name="address"   id="address"   value="{{ $location['address']   ?? '' }}">

            {{-- Save --}}
            <button type="submit"
                    id="saveLocationBtn"
                    class="w-full mt-5 bg-[#191970] hover:bg-[#2a2a8a] text-[#D9F855] text-base font-medium py-2.5 rounded-xl transition">
                <i class="fas fa-floppy-disk mr-2"></i>Save Location
            </button>
        </form>
    </div>
</div>
@endsection
