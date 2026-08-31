@extends('layouts.app')

@section('content')
<div class="min-h-full flex flex-col items-center justify-center bg-[#f0f0ff] px-4 py-16">

    <!-- Icon -->
    <div class="bg-[#191970] rounded-2xl p-5 mb-6 shadow-lg">
        <i class="fas fa-clinic-medical text-[#D9F855] text-4xl"></i>
    </div>

    <!-- Card -->
    <div class="w-full max-w-md bg-white rounded-2xl border border-[#9400D3]/10 shadow-xl overflow-hidden">
        <div class="bg-[#191970] px-6 py-4 text-center">
            <h1 class="text-xl font-bold text-[#D9F855] tracking-tight">Registration Submitted</h1>
            <p class="text-[#D9F855]/60 text-xs mt-1">MedFind Pharmacy Partner</p>
        </div>

        <div class="p-8 text-center">
            <!-- Status badge -->
            <div class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-4 py-1.5 rounded-full mb-6">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                Pending Approval
            </div>

            <h2 class="text-lg font-semibold text-[#191970] mb-3">
                Your account is under review
            </h2>

            <p class="text-sm text-gray-500 leading-relaxed mb-6">
                Thank you for registering your pharmacy with MedFind. Our admin team will review your
                details and activate your account shortly. You'll be notified once approved.
            </p>

            <!-- Steps -->
            <div class="text-left space-y-3 mb-8">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-[#191970] flex items-center justify-center mt-0.5">
                        <i class="fas fa-check text-[#D9F855] text-xs"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-[#191970]">Registration submitted</p>
                        <p class="text-xs text-gray-400">Your pharmacy details have been received.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-amber-100 border-2 border-amber-400 flex items-center justify-center mt-0.5">
                        <i class="fas fa-clock text-amber-500 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-[#191970]">Admin review</p>
                        <p class="text-xs text-gray-400">Our team is verifying your pharmacy information.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-[#f8f4ff] border-2 border-[#9400D3]/20 flex items-center justify-center mt-0.5">
                        <i class="fas fa-store text-[#9400D3]/40 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Account activated</p>
                        <p class="text-xs text-gray-400">Access your pharmacy dashboard once approved.</p>
                    </div>
                </div>
            </div>

            <!-- Logout -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full bg-[#191970] text-[#D9F855] text-base font-medium py-3 rounded-lg hover:bg-[#2a2a8a] transition">
                    <i class="fas fa-sign-out-alt mr-2"></i>Sign out
                </button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <p class="text-xs text-[#9400D3]/30 font-light mt-8">
        &copy; 2026 MedFind. All rights reserved.
    </p>
</div>
@endsection
