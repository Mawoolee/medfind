{{-- resources/views/admin/add-pharmacy.blade.php --}}

@extends('layouts.app')

@section('title', 'Add Pharmacy')

@section('content')
<style>
    .location-confirmed-map {
        background-color: #f0fdf4;
        background-image:
            linear-gradient(rgba(220, 252, 231, 0.7), rgba(220, 252, 231, 0.7)),
            url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='260'%20height='260'%20viewBox='0%200%20260%20260'%3E%3Crect%20width='260'%20height='260'%20fill='%23f0fdf4'/%3E%3Cg%20fill='%234ade80'%20fill-opacity='0.27'%3E%3Cpath%20d='M150%2018%20C172%2028%20182%2052%20176%2074%20C170%2094%20146%20102%20126%2092%20C110%2084%20106%2062%20118%2044%20C126%2032%20138%2022%20150%2018%20Z'/%3E%3Crect%20x='40'%20y='150'%20width='52'%20height='40'%20rx='10'/%3E%3C/g%3E%3Cg%20fill='%2386efac'%20fill-opacity='0.27'%3E%3Ccircle%20cx='202'%20cy='196'%20r='20'/%3E%3Cpath%20d='M28%2050%20L64%2044%20L72%2074%20L44%2088%20L20%2072%20Z'/%3E%3C/g%3E%3Cg%20fill='none'%20stroke='%23475569'%20stroke-opacity='0.35'%20stroke-linecap='round'%3E%3Cpath%20stroke-width='6.5'%20d='M-20%2074%20C40%2050%2080%20110%20140%2098%20C200%2086%20230%20130%20290%20112'/%3E%3Cpath%20stroke-width='6'%20d='M60%20-20%20C74%2050%2030%2090%2058%20150%20C82%20202%2050%20230%2074%20290'/%3E%3C/g%3E%3Cg%20fill='none'%20stroke='%2364748b'%20stroke-opacity='0.35'%20stroke-linecap='round'%3E%3Cpath%20stroke-width='3.5'%20d='M-20%20200%20C50%20186%2090%20226%20150%20208%20C210%20190%20240%20214%20290%20198'/%3E%3Cpath%20stroke-width='3'%20d='M186%20-20%20C176%2040%20214%2080%20200%20140%20C188%20192%20214%20226%20204%20290'/%3E%3Cpath%20stroke-width='2.5'%20d='M120%2098%20Q132%20140%20168%20150'/%3E%3Cpath%20stroke-width='2.5'%20d='M58%20120%20Q90%20132%2094%20176'/%3E%3Cpath%20stroke-width='2'%20d='M200%20120%20Q168%20128%20150%20160'/%3E%3C/g%3E%3C/svg%3E");
        background-size: 260px 260px;
        background-repeat: repeat;
    }
    .location-edit-pen {
        color: #4b5563;
        transition: color 0.15s;
    }
    .location-edit-pen:hover {
        color: #7a00b0;
    }
</style>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6 max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800">Add Pharmacy</h1>
        <a href="{{ route('admin.pharmacies') }}" class="text-[#9400D3] hover:text-[#7a00b0]">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4 max-w-2xl mx-auto">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-lg p-5 sm:p-6 max-w-2xl mx-auto">
        <form action="{{ route('admin.pharmacy.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="pharmacy_name" class="block text-gray-700 text-sm font-medium mb-2">Pharmacy Name</label>
                <input type="text" id="pharmacy_name" name="pharmacy_name" value="{{ old('pharmacy_name') }}" required
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="pharmacyAddress" class="block text-gray-700 text-sm font-medium mb-2">Address</label>
                <input type="text" id="pharmacyAddress" name="pharmacyAddress" value="{{ old('pharmacyAddress') }}" required
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="contactNumber" class="block text-gray-700 text-sm font-medium mb-2">Contact Number</label>
                <input type="text" id="contactNumber" name="contactNumber" value="{{ old('contactNumber') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="mb-4">
                <label for="operating_hours" class="block text-gray-700 text-sm font-medium mb-2">Operating Hours</label>
                <input type="text" id="operating_hours" name="operating_hours" value="{{ old('operating_hours') }}"
                       placeholder="e.g. Mon-Sat 8:00 AM - 9:00 PM"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            @php
                $lat  = old('latitude',  session('admin_add_pharmacy.location.latitude'));
                $lng  = old('longitude', session('admin_add_pharmacy.location.longitude'));
                $addr = old('location_address', session('admin_add_pharmacy.location.address'));
                $hasLocation = $lat !== null && $lat !== '' && $lng !== null && $lng !== '';
            @endphp
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-medium mb-2">Pharmacy Location</label>

                {{-- Confirmed state --}}
                @if ($hasLocation)
                <div class="location-confirmed-map relative mb-2 overflow-hidden rounded-xl border border-green-300 px-3 py-2.5">
                    <a href="{{ route('admin.pharmacy.location') }}"
                       aria-label="Change location"
                       title="Change location"
                       class="location-edit-pen absolute top-2.5 right-3 z-20 inline-flex items-center justify-center">
                        <i class="fas fa-pen text-sm"></i>
                    </a>
                    <div class="relative z-10">
                        <div class="flex items-center gap-1.5 pr-8 text-sm font-semibold text-green-700">
                            <i class="fas fa-circle-check"></i>
                            Location confirmed
                        </div>
                        @if ($addr)
                            <p class="mt-1 pr-8 text-xs text-gray-600 leading-snug">{{ $addr }}</p>
                        @endif
                    </div>
                </div>
                @else
                <a href="{{ route('admin.pharmacy.location') }}"
                   class="group flex w-full flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-purple-400/40 bg-purple-50/30 px-4 py-6 text-center cursor-pointer transition hover:border-purple-500 hover:bg-purple-50">
                    <i class="fas fa-map-location-dot text-2xl text-purple-500"></i>
                    <span class="text-sm font-semibold text-gray-700">Set Pharmacy Location</span>
                    <span class="text-xs font-light text-purple-500/70">Search address or drop a pin on the map</span>
                </a>
                @endif

                {{-- Hidden inputs submitted with the form --}}
                <input type="hidden" name="latitude"         value="{{ $hasLocation ? $lat : '' }}">
                <input type="hidden" name="longitude"        value="{{ $hasLocation ? $lng : '' }}">
                <input type="hidden" name="location_address" value="{{ $addr }}">

                @error('latitude')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                @error('longitude')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="user_id" class="block text-gray-700 text-sm font-medium mb-2">Owner (optional)</label>
                <select id="user_id" name="user_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- No owner --</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit" class="inline-flex items-center justify-center bg-blue-700 hover:bg-blue-800 text-white px-6 py-2.5 rounded-xl transition duration-200 w-full sm:w-auto">
                    <i class="fas fa-plus mr-2"></i>Add Pharmacy
                </button>
                <a href="{{ route('admin.pharmacies') }}" class="inline-flex items-center justify-center border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-2.5 rounded-xl transition duration-200 w-full sm:w-auto">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
