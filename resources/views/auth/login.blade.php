﻿<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <!-- Logo -->
        <div class="auth-logo-container mb-4 text-center px-6 pt-0 pb-3 overflow-hidden rounded-xl backdrop-blur-xl border border-gray-200/50 dark:border-[rgba(0,220,255,0.45)] shadow-lg dark:shadow-[0_0_18px_rgba(0,200,255,0.15),0_8px_32px_rgba(0,0,0,0.5)]" style="background-color: rgba(255,255,255,0.45); backdrop-filter: blur(12px);">
            <div class="flex items-center justify-center gap-0 overflow-hidden">
                <img src="{{ asset('images/MedFind Final Icon.png') }}" alt="MedFind Icon" class="h-16 sm:h-20 w-auto -mr-9">
                <img src="{{ asset('images/Final Logo MedFind.png') }}" alt="MedFind" class="h-36 sm:h-52 w-auto -my-10 sm:-my-16" style="filter: drop-shadow(0 0 0.5px white) drop-shadow(0 0 0.5px white);">
            </div>
            <p class="text-lg sm:text-base text-gray-700 dark:text-white/90 font-light mt-1">Sign in to your account</p>
        </div>

        <!-- Card -->
        <div class="auth-card-container auth-card w-full max-w-sm rounded-xl overflow-hidden backdrop-blur-xl border border-gray-200/50 dark:border-[rgba(0,220,255,0.45)] shadow-lg dark:shadow-[0_0_18px_rgba(0,200,255,0.15),0_8px_32px_rgba(0,0,0,0.5)]">
            <div class="p-6">
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wider mb-1.5">
                            Email
                        </label>
                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="username"
                               class="w-full px-4 py-3 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                               placeholder="Email">
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wider mb-1.5">
                            Password
                        </label>
                        <div class="relative">
                            <input id="password"
                                   type="password"
                                   name="password"
                                   required
                                   autocomplete="current-password"
                                   class="w-full px-4 py-3 pr-10 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                                   placeholder="••••••••">
                            <button type="button" class="password-toggle-icon absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-white/40 hover:text-gray-600 dark:hover:text-white/70 focus:outline-none" onclick="togglePassword(this)" tabindex="-1" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-white/80 cursor-pointer">
                            <input type="checkbox" name="remember" class="w-4 h-4 border-gray-300 dark:border-white/30 text-[#191970] focus:ring-blue-500 dark:focus:ring-white/30">
                            <span class="font-light">Remember me</span>
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm text-blue-600 dark:text-[#D9F855] hover:text-blue-800 dark:hover:text-white transition font-medium">
                                Forgot password?
                            </a>
                        @endif
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-[#191970] text-[#D9F855] text-base font-medium py-3 rounded-lg hover:bg-[#2a2a8a] transition">
                        Sign in &rarr;
                    </button>

                    <!-- Register Link -->
                    <p class="text-center text-sm text-gray-600 dark:text-white/90 font-normal mt-4">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="text-blue-600 dark:text-white hover:text-blue-800 dark:hover:text-[#D9F855] transition font-bold">
                            Sign up
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

    <script>
        function togglePassword(btn) {
            const input = btn.closest('.relative').querySelector('input');
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</x-guest-layout>