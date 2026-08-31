<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center bg-[#f0f0ff] px-4">
        <!-- Logo -->
        <div class="mb-8 text-center">
            <div class="flex items-center justify-center gap-2 mb-2">
                <div class="bg-[#191970] rounded-lg p-2">
                    <i class="fas fa-clinic-medical text-[#D9F855] text-xl"></i>
                </div>
                <img src="{{ asset('images/Final Logo MedFind.png') }}" alt="MedFind" class="h-24 sm:h-32 w-auto">
            </div>
            <p class="text-base sm:text-sm text-[#9400D3] font-light">Create your account</p>
            <p class="text-xs text-[#9400D3]/60 font-light mt-1">Pharmacy Owner &middot; Step 1 of 2</p>
        </div>

        <!-- Card -->
        <div class="w-full max-w-sm bg-white rounded-xl border border-[#9400D3]/10 shadow-lg overflow-hidden">
            <div class="p-6">
                <form method="POST" action="{{ route('register.pharmacy.account') }}">
                    @csrf

                    <!-- Name -->
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                            Full name
                        </label>
                        <input id="name"
                               type="text"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               autofocus
                               autocomplete="name"
                               class="w-full px-4 py-3 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-base text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                               placeholder="Full Name">
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                            Email
                        </label>
                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autocomplete="username"
                               class="w-full px-4 py-3 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-base text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                               placeholder="Email or mobile number">
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                            Password
                        </label>
                        <input id="password"
                               type="password"
                               name="password"
                               required
                               autocomplete="new-password"
                               class="w-full px-4 py-3 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-base text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                               placeholder="••••••••">
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-5">
                        <label for="password_confirmation" class="block text-sm font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                            Confirm password
                        </label>
                        <input id="password_confirmation"
                               type="password"
                               name="password_confirmation"
                               required
                               autocomplete="new-password"
                               class="w-full px-4 py-3 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-base text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                               placeholder="••••••••">
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-[#191970] text-[#D9F855] text-base font-medium py-3 rounded-lg hover:bg-[#2a2a8a] transition">
                        Continue
                    </button>

                    <!-- Consumer / Login Links -->
                    <p class="text-center text-sm text-gray-400 font-light mt-4">
                        Not a pharmacy owner?
                        <a href="{{ route('register') }}" class="text-[#9400D3] hover:text-[#191970] transition font-medium">
                            Consumer sign up
                        </a>
                    </p>
                    <p class="text-center text-sm text-gray-400 font-light mt-2">
                        Already have an account?
                        <a href="{{ route('login') }}" class="text-[#9400D3] hover:text-[#191970] transition font-medium">
                            Sign in
                        </a>
                    </p>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-xs text-[#9400D3]/30 font-light mt-8">
            &copy; 2026 MedFind. All rights reserved.
        </p>
    </div>
</x-guest-layout>
