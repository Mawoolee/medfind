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
        <a href="{{ route('pharmacy.dashboard') }}"
            class="flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-full border transition-colors hover:bg-white"
            style="color:#191970;border-color:#191970;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
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
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 transition-shadow"
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
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 transition-shadow">
                    @error('contactNumber')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">
                        Email Address
                    </label>
                    <input type="email" name="email"
                        value="{{ old('email', $user->email) }}"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 transition-shadow">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">
                        Address
                    </label>
                    <textarea name="pharmacyAddress" rows="2"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 transition-shadow resize-none">{{ old('pharmacyAddress', $pharmacy->pharmacyAddress) }}</textarea>
                    @error('pharmacyAddress')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Location --}}
        <div class="bg-white rounded-[20px] shadow-sm p-6 mb-6 border border-gray-100">
            <h2 class="font-semibold mb-4" style="color:#191970;">
                <i class="fas fa-map-pin mr-2" style="color:#9400D3;"></i>
                Location Coordinates
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">Latitude</label>
                    <input type="number" step="any" name="latitude"
                        value="{{ old('latitude', $pharmacy->latitude) }}"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 transition-shadow">
                    @error('latitude')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#191970;">Longitude</label>
                    <input type="number" step="any" name="longitude"
                        value="{{ old('longitude', $pharmacy->longitude) }}"
                        class="mt-1 block w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 transition-shadow">
                    @error('longitude')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
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
