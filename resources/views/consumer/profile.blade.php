@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f0f0ff] py-8 px-4 font-sans">
    <div class="max-w-xl mx-auto space-y-5">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-xl sm:text-2xl font-extrabold text-[#191970]">
                <i class="fas fa-user-circle mr-2 text-[#9400D3]"></i>Profile Settings
            </h1>
            <a href="{{ route('consumer.dashboard') }}"
               class="text-sm text-[#9400D3] hover:text-[#191970] font-semibold transition flex items-center gap-1">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        @if(session('status') === 'profile-updated')
            <div class="bg-green-50 border border-green-300 text-green-700 text-xs font-semibold px-4 py-3 rounded-xl">
                ✅ Profile updated successfully.
            </div>
        @endif
        @if(session('status') === 'password-updated')
            <div class="bg-green-50 border border-green-300 text-green-700 text-xs font-semibold px-4 py-3 rounded-xl">
                ✅ Password updated successfully.
            </div>
        @endif

        {{-- Update Profile Info --}}
        <div class="bg-white rounded-[20px] shadow-sm border border-[#9400D3]/10 p-6">
            <h2 class="text-sm font-extrabold text-[#191970] mb-1">
                <i class="fas fa-id-card mr-2 text-[#9400D3]"></i>Personal Information
            </h2>
            <p class="text-xs text-gray-400 mb-4">Update your name and email address.</p>

            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Full Name</label>
                    <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required
                        class="w-full border border-[#9400D3]/20 rounded-xl px-4 py-2.5 text-base text-[#191970] outline-none focus:border-[#9400D3] bg-[#f8f4ff] transition">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required
                        class="w-full border border-[#9400D3]/20 rounded-xl px-4 py-2.5 text-base text-[#191970] outline-none focus:border-[#9400D3] bg-[#f8f4ff] transition">
                    @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                    class="w-full bg-[#191970] hover:bg-[#2a2a8a] text-[#D9F855] font-bold py-2.5 rounded-full text-sm transition active:scale-95">
                    Save Changes
                </button>
            </form>
        </div>

        {{-- Update Password --}}
        <div class="bg-white rounded-[20px] shadow-sm border border-[#9400D3]/10 p-6">
            <h2 class="text-sm font-extrabold text-[#191970] mb-1">
                <i class="fas fa-lock mr-2 text-[#9400D3]"></i>Change Password
            </h2>
            <p class="text-xs text-gray-400 mb-4">Use a strong password to keep your account secure.</p>

            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Current Password</label>
                    <input type="password" name="current_password" required
                        class="w-full border border-[#9400D3]/20 rounded-xl px-4 py-2.5 text-base text-[#191970] outline-none focus:border-[#9400D3] bg-[#f8f4ff] transition">
                    @error('current_password', 'updatePassword') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">New Password</label>
                    <input type="password" name="password" required
                        class="w-full border border-[#9400D3]/20 rounded-xl px-4 py-2.5 text-base text-[#191970] outline-none focus:border-[#9400D3] bg-[#f8f4ff] transition">
                    @error('password', 'updatePassword') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Confirm New Password</label>
                    <input type="password" name="password_confirmation" required
                        class="w-full border border-[#9400D3]/20 rounded-xl px-4 py-2.5 text-base text-[#191970] outline-none focus:border-[#9400D3] bg-[#f8f4ff] transition">
                </div>

                <button type="submit"
                    class="w-full bg-[#9400D3] hover:bg-[#7a00b0] text-white font-bold py-2.5 rounded-full text-sm transition active:scale-95">
                    Update Password
                </button>
            </form>
        </div>

        {{-- Danger Zone --}}
        <div class="bg-white rounded-[20px] shadow-sm border border-red-200 p-6">
            <h2 class="text-sm font-extrabold text-red-600 mb-1">
                <i class="fas fa-triangle-exclamation mr-2"></i>Danger Zone
            </h2>
            <p class="text-xs text-gray-400 mb-4">Permanently delete your account and all associated data. This cannot be undone.</p>

            <form method="POST" action="{{ route('profile.destroy') }}"
                  onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                @csrf
                @method('DELETE')
                <input type="password" name="password" placeholder="Enter your password to confirm"
                    class="w-full border border-red-200 rounded-xl px-4 py-2.5 text-base text-[#191970] outline-none focus:border-red-400 bg-red-50 transition mb-3">
                @error('password', 'userDeletion') <p class="text-red-500 text-xs mb-2">{{ $message }}</p> @enderror
                <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-full text-sm transition active:scale-95">
                    Delete My Account
                </button>
            </form>
        </div>

    </div>
</div>
@endsection