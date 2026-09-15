﻿<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <!-- Logo -->
        <div class="auth-logo-container mb-4 text-center px-6 pt-0 pb-3 overflow-hidden rounded-xl border border-gray-200/50 dark:border-[rgba(0,220,255,0.45)] shadow-lg dark:shadow-[0_0_18px_rgba(0,200,255,0.15),0_8px_32px_rgba(0,0,0,0.5)]">
            <div class="flex items-center justify-center gap-0 overflow-hidden">
                <img src="{{ asset('images/MedFind Final Icon.png') }}" alt="MedFind Icon" class="h-16 sm:h-20 w-auto -mr-9">
                <img src="{{ asset('images/Final Logo MedFind.png') }}" alt="MedFind" class="h-36 sm:h-52 w-auto -my-10 sm:-my-16" style="filter: drop-shadow(0 0 0.5px white) drop-shadow(0 0 0.5px white);">
            </div>
            <p class="text-lg sm:text-base text-gray-700 dark:text-white/90 font-light mt-1">Create your account</p>
            <p class="text-xs text-gray-500 dark:text-white/60 font-light mt-0.5">Pharmacy Owner &middot; Step 1 of 2</p>
        </div>

        <!-- Card -->
        <div class="auth-card-container auth-card w-full max-w-sm rounded-xl overflow-hidden backdrop-blur-xl border border-gray-200/50 dark:border-[rgba(0,220,255,0.45)] shadow-lg dark:shadow-[0_0_18px_rgba(0,200,255,0.15),0_8px_32px_rgba(0,0,0,0.5)]">
            <div class="p-6">
                <form method="POST" action="{{ route('register.pharmacy.account') }}">
                    @csrf

                    <!-- Name -->
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wider mb-1.5">
                            Full name
                        </label>
                        <input id="name"
                               type="text"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               autofocus
                               autocomplete="name"
                               class="w-full px-4 py-3 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                               placeholder="Full Name">
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

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
                               autocomplete="username"
                               class="w-full px-4 py-3 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                               placeholder="Email or mobile number">
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
                                   autocomplete="new-password"
                                   class="w-full px-4 py-3 pr-10 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                                   placeholder="••••••••">
                            <button type="button" class="password-toggle-icon absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-white/40 hover:text-gray-600 dark:hover:text-white/70 focus:outline-none" onclick="togglePassword(this)" tabindex="-1" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-5">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wider mb-1.5">
                            Confirm password
                        </label>
                        <div class="relative">
                            <input id="password_confirmation"
                                   type="password"
                                   name="password_confirmation"
                                   required
                                   autocomplete="new-password"
                                   class="w-full px-4 py-3 pr-10 bg-gray-50 dark:bg-white/10 border border-gray-300 dark:border-white/20 rounded-lg text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:focus:ring-white/30 focus:border-blue-500 dark:focus:border-white/50 transition"
                                   placeholder="••••••••">
                            <button type="button" class="password-toggle-icon absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-white/40 hover:text-gray-600 dark:hover:text-white/70 focus:outline-none" onclick="togglePassword(this)" tabindex="-1" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-[#191970] text-[#D9F855] text-base font-medium py-3 rounded-lg hover:bg-[#2a2a8a] transition">
                        Continue
                    </button>

                    <!-- Consumer / Login Links -->
                    <p class="text-center text-sm text-gray-600 dark:text-white/90 font-normal mt-4">
                        Not a pharmacy owner?
                        <a href="{{ route('register') }}" class="text-blue-600 dark:text-white hover:text-blue-800 dark:hover:text-[#D9F855] transition font-bold">
                            Consumer sign up
                        </a>
                    </p>
                    <p class="text-center text-sm text-gray-600 dark:text-white/90 font-normal mt-2">
                        Already have an account?
                        <a href="{{ route('login') }}" class="text-blue-600 dark:text-white hover:text-blue-800 dark:hover:text-[#D9F855] transition font-bold">
                            Sign in
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
