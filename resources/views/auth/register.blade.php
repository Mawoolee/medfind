<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center bg-[#f0f0ff] px-4">
        <!-- Logo -->
        <div class="mb-2 text-center">
            <div class="flex items-center justify-center mb-0 overflow-hidden">
                <img src="{{ asset('images/Final Logo MedFind.png') }}" alt="MedFind" class="h-28 sm:h-40 w-auto -my-8 sm:-my-12">
            </div>
            <p class="text-base sm:text-sm text-[#9400D3] font-light mt-1">Create your account</p>
        </div>

        <!-- Card -->
        <div class="w-full max-w-sm bg-white rounded-xl border border-[#9400D3]/10 shadow-lg overflow-hidden">
            <div class="p-6">
                <form method="POST" action="{{ route('register') }}">
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
                               placeholder="Email">
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
                        Create account
                    </button>

                    <!-- Login Link -->
                    <p class="text-center text-sm text-gray-400 font-light mt-4">
                        Already have an account?
                        <a href="{{ route('login') }}" class="text-[#9400D3] hover:text-[#191970] transition font-medium">
                            Sign in
                        </a>
                    </p>

                    <!-- Pharmacy Owner Link -->
                    <p class="text-center text-sm text-gray-400 font-light mt-2">
                        Are you a Pharmacy Owner?
                        <a href="{{ route('register.pharmacy') }}" class="text-[#9400D3] hover:text-[#191970] transition font-medium">
                            Register here.
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
