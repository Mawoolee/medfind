@extends('layouts.app')

@section('title', 'Pharmacy Profile')

@section('content')
<div class="min-h-screen" style="background:#f0f0ff;">
<div class="container mx-auto px-4 py-10 max-w-2xl">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background:#191970;">
                <i class="fas fa-hospital text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold" style="color:#191970;">Pharmacy Profile</h1>
                <p class="text-sm text-gray-500">Manage your pharmacy information</p>
            </div>
        </div>
        <x-back-button :href="route('pharmacy.dashboard')" label="Back to Pharmacy Dashboard" />
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-xl mb-5 flex items-center gap-2">
            <i class="fas fa-check-circle text-green-500"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-xl mb-5 flex items-center gap-2">
            <i class="fas fa-exclamation-circle text-red-500"></i>
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('pharmacy.profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Logo / avatar section --}}
        <div class="bg-white rounded-[20px] shadow-sm p-6 mb-5 border border-gray-100">
            <h2 class="font-semibold mb-4" style="color:#191970;">
                <i class="fas fa-image mr-2" style="color:#9400D3;"></i>
                Pharmacy Logo
            </h2>
            <div class="flex items-center gap-5">
                {{-- Preview --}}
                @if($pharmacy->logo_path)
                    <img src="{{ asset('storage/' . $pharmacy->logo_path) }}"
                        alt="Pharmacy Logo"
                        class="w-24 h-24 rounded-full object-cover border-4"
                        style="border-color:rgba(148,0,211,0.2);"
                        id="logoPreview">
                @else
                    <div class="w-24 h-24 rounded-full flex items-center justify-center border-4 flex-shrink-0"
                        style="background:#f0f0ff;border-color:rgba(148,0,211,0.2);"
                        id="logoPlaceholder">
                        <i class="fas fa-store text-3xl" style="color:#9400D3;opacity:0.5;"></i>
                    </div>
                    <img src="" alt="Logo Preview" class="w-24 h-24 rounded-full object-cover border-4 hidden"
                        style="border-color:rgba(148,0,211,0.2);"
                        id="logoPreview">
                @endif

                <div class="flex-1">
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">
                        Upload New Logo
                    </label>
                    <input type="file"
                        name="logo"
                        id="logoInput"
                        accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                        class="block w-full text-sm text-gray-500
                            file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0
                            file:text-sm file:font-semibold file:text-white file:cursor-pointer
                            hover:file:opacity-90"
                        style="--file-bg:#9400D3;"
                        onchange="previewLogo(this)">
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, WEBP — max 2MB</p>
                    @error('logo')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Status badge (read-only) --}}
        <div class="bg-white rounded-[20px] shadow-sm p-4 mb-5 border border-gray-100 flex items-center gap-3">
            <i class="fas fa-shield-halved" style="color:#9400D3;"></i>
            <span class="text-sm font-medium" style="color:#191970;">Account Status:</span>
            @php
                $statusColors = [
                    'pending'  => 'bg-amber-100 text-amber-800 border-amber-300',
                    'approved' => 'bg-green-100 text-green-800 border-green-300',
                    'rejected' => 'bg-red-100 text-red-800 border-red-300',
                    'inactive' => 'bg-gray-100 text-gray-700 border-gray-300',
                ];
                $statusColor = $statusColors[$pharmacy->status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
            @endphp
            <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $statusColor }}">
                {{ ucfirst($pharmacy->status) }}
            </span>
            @if($pharmacy->status === 'pending')
                <a href="{{ route('pharmacy.requirements') }}" class="ml-auto text-xs font-semibold hover:underline" style="color:#9400D3;">
                    Upload Requirements →
                </a>
            @endif
        </div>

        {{-- Pharmacy details --}}
        <div class="bg-white rounded-[20px] shadow-sm p-6 mb-5 border border-gray-100">
            <h2 class="font-semibold mb-4" style="color:#191970;">
                <i class="fas fa-info-circle mr-2" style="color:#9400D3;"></i>
                Pharmacy Details
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">
                        Pharmacy Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="pharmacy_name"
                        value="{{ old('pharmacy_name', $pharmacy->pharmacy_name) }}"
                        required
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-base focus:outline-none focus:ring-2 transition-shadow"
                        style="focus:ring-color:#9400D3;">
                    @error('pharmacy_name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">
                        Contact Number
                    </label>
                    <input type="text" name="contactNumber"
                        value="{{ old('contactNumber', $pharmacy->contactNumber) }}"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-base focus:outline-none focus:ring-2 transition-shadow">
                    @error('contactNumber')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">
                        Operating Hours
                    </label>
                    <input type="text" name="operating_hours"
                        value="{{ old('operating_hours', $pharmacy->operating_hours) }}"
                        placeholder="e.g. Mon-Sat 8:00 AM - 9:00 PM"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-base focus:outline-none focus:ring-2 transition-shadow">
                    @error('operating_hours')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">
                        Email Address
                    </label>
                    <input type="email" name="email"
                        value="{{ old('email', $user->email) }}"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-base focus:outline-none focus:ring-2 transition-shadow">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">
                        Address
                    </label>
                    <textarea name="pharmacyAddress" rows="2"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2.5 text-base focus:outline-none focus:ring-2 transition-shadow resize-none">{{ old('pharmacyAddress', $pharmacy->pharmacyAddress) }}</textarea>
                    @error('pharmacyAddress')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Location --}}
        @php
            // Prefer a freshly-picked location (from the map page) then old()
            // input, then the persisted pharmacy values.
            $lat = old('latitude', $location['latitude'] ?? $pharmacy->latitude);
            $lng = old('longitude', $location['longitude'] ?? $pharmacy->longitude);
            $addr = old('location_address', $location['address'] ?? null);
            $hasLocation = $lat !== null && $lat !== '' && $lng !== null && $lng !== '';
        @endphp
        <div class="bg-white rounded-[20px] shadow-sm p-6 mb-6 border border-gray-100">
            <h2 class="font-semibold mb-4" style="color:#191970;">
                <i class="fas fa-map-pin mr-2" style="color:#9400D3;"></i>
                Location Coordinates
            </h2>

            {{-- Confirmed state (shown when a location is set) --}}
            <div id="locationConfirmed" class="{{ $hasLocation ? '' : 'hidden' }} location-confirmed-map relative mb-3 overflow-hidden rounded-xl border border-green-300 px-4 py-3">
                {{-- Pen (edit) link: the "Change location" affordance when a location is set --}}
                <a href="{{ route('pharmacy.profile.location') }}"
                    aria-label="Change location"
                    title="Change location"
                    class="location-edit-pen absolute top-2 right-2 z-10 flex items-center justify-center p-2 transition">
                    <i class="fas fa-pen text-xs"></i>
                </a>
                <div class="relative z-10">
                    <div class="flex items-center gap-2 pr-9 text-sm font-semibold text-green-700">
                        <i class="fas fa-circle-check"></i>
                        Location confirmed
                    </div>
                    @if ($addr)
                        <p class="mt-1 pr-9 text-sm text-[#191970] leading-snug">{{ $addr }}</p>
                    @endif
                </div>
            </div>

            {{-- Empty-state hint id preserved for JS; helper text now lives in the card. --}}
            <p id="locationHint" class="{{ $hasLocation ? 'hidden' : '' }} hidden text-xs text-gray-400 mb-3">
                No location set yet.
            </p>

            {{-- Full-width clickable card linking to the separate map page (empty state only) --}}
            @if (! $hasLocation)
            <a href="{{ route('pharmacy.profile.location') }}"
                class="group flex w-full flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed px-4 py-6 text-center cursor-pointer transition hover:bg-[#9400D3]/5"
                style="border-color:rgba(148,0,211,0.4);">
                <i class="fas fa-map-location-dot text-2xl" style="color:#9400D3;"></i>
                <span class="text-base font-semibold" style="color:#191970;">Set Pharmacy Location</span>
                <span class="text-sm font-light" style="color:rgba(148,0,211,0.7);">Search address or drop a pin on the map</span>
            </a>
            @endif

            {{-- Coordinates submitted with the form as hidden inputs. --}}
            <input type="hidden" name="latitude" id="latitude" value="{{ $hasLocation ? $lat : '' }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ $hasLocation ? $lng : '' }}">
            <input type="hidden" name="location_address" id="location_address" value="{{ $addr }}">

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
            <i class="fas fa-floppy-disk"></i>
            Save Profile
        </button>
    </form>

</div>
</div>

<style>
input[type="file"]::file-selector-button {
    background: #9400D3;
    color: #fff;
    border: none;
    padding: 6px 16px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
input[type="file"]::file-selector-button:hover {
    opacity: 0.9;
}

/* Decorative faded street-map texture for the confirmed-location box (CSS only, no external images). */
.location-confirmed-map {
    background-color: #f0fdf4;
    /* Faint street-map look: translucent green tint layered over an inline SVG
       that draws stylized roads, blocks and a small park. Pure CSS, no external
       images. SVG is URL-encoded so no raw < > # or spaces appear in the data
       URI, and it contains no curly braces (Blade-safe). */
    background-image:
        linear-gradient(rgba(220, 252, 231, 0.7), rgba(220, 252, 231, 0.7)),
        url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='260'%20height='260'%20viewBox='0%200%20260%20260'%3E%3Crect%20width='260'%20height='260'%20fill='%23f0fdf4'/%3E%3Cg%20fill='%234ade80'%20fill-opacity='0.27'%3E%3Cpath%20d='M150%2018%20C172%2028%20182%2052%20176%2074%20C170%2094%20146%20102%20126%2092%20C110%2084%20106%2062%20118%2044%20C126%2032%20138%2022%20150%2018%20Z'/%3E%3Crect%20x='40'%20y='150'%20width='52'%20height='40'%20rx='10'/%3E%3C/g%3E%3Cg%20fill='%2386efac'%20fill-opacity='0.27'%3E%3Ccircle%20cx='202'%20cy='196'%20r='20'/%3E%3Cpath%20d='M28%2050%20L64%2044%20L72%2074%20L44%2088%20L20%2072%20Z'/%3E%3C/g%3E%3Cg%20fill='none'%20stroke='%23475569'%20stroke-opacity='0.35'%20stroke-linecap='round'%3E%3Cpath%20stroke-width='6.5'%20d='M-20%2074%20C40%2050%2080%20110%20140%2098%20C200%2086%20230%20130%20290%20112'/%3E%3Cpath%20stroke-width='6'%20d='M60%20-20%20C74%2050%2030%2090%2058%20150%20C82%20202%2050%20230%2074%20290'/%3E%3C/g%3E%3Cg%20fill='none'%20stroke='%2364748b'%20stroke-opacity='0.35'%20stroke-linecap='round'%3E%3Cpath%20stroke-width='3.5'%20d='M-20%20200%20C50%20186%2090%20226%20150%20208%20C210%20190%20240%20214%20290%20198'/%3E%3Cpath%20stroke-width='3'%20d='M186%20-20%20C176%2040%20214%2080%20200%20140%20C188%20192%20214%20226%20204%20290'/%3E%3Cpath%20stroke-width='2.5'%20d='M120%2098%20Q132%20140%20168%20150'/%3E%3Cpath%20stroke-width='2.5'%20d='M58%20120%20Q90%20132%2094%20176'/%3E%3Cpath%20stroke-width='2'%20d='M200%20120%20Q168%20128%20150%20160'/%3E%3C/g%3E%3C/svg%3E");
    background-size: 260px 260px;
    background-repeat: repeat;
}

/* Pen (edit) link: plain purple icon, darker purple on hover, no white circle. */
.location-edit-pen {
    color: #9400D3;
}
.location-edit-pen:hover {
    color: #191970;
}
</style>

<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('logoPreview');
            const placeholder = document.getElementById('logoPlaceholder');
            if (preview) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            if (placeholder) {
                placeholder.classList.add('hidden');
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endsection
