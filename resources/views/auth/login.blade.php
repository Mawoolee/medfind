<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center bg-[#f0f0ff] px-4">
        <!-- Logo -->
        <div class="mb-4 text-center">
            <div class="flex items-center justify-center mb-1">
                <img src="{{ asset('images/Final Logo MedFind.png') }}" alt="MedFind" class="h-48 w-auto">
            </div>
            <p class="text-sm text-[#9400D3] font-light">Sign in to your account</p>
        </div>

        <!-- Card -->
        <div class="w-full max-w-sm bg-white rounded-xl border border-[#9400D3]/10 shadow-lg overflow-hidden">
            <div class="p-6">
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email" class="block text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                            Email
                        </label>
                        <input id="email" 
                               type="email" 
                               name="email" 
                               value="{{ old('email') }}" 
                               required 
                               autofocus 
                               autocomplete="username"
                               class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                               placeholder="Email or mobile number">
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="block text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                            Password
                        </label>
                        <input id="password" 
                               type="password" 
                               name="password" 
                               required 
                               autocomplete="current-password"
                               class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                               placeholder="••••••••">
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center gap-2 text-sm text-gray-500 cursor-pointer">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-[#9400D3]/30 text-[#191970] focus:ring-[#9400D3]">
                            <span class="font-light">Remember me</span>
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm text-[#9400D3] hover:text-[#191970] transition font-light">
                                Forgot password?
                            </a>
                        @endif
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-[#191970] text-[#D9F855] text-sm font-medium py-2.5 rounded-lg hover:bg-[#2a2a8a] transition">
                        Sign in
                    </button>

                    <!-- Register Link -->
                    <p class="text-center text-sm text-gray-400 font-light mt-4">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="text-[#9400D3] hover:text-[#191970] transition font-medium">
                            Sign up
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