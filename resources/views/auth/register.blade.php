<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center bg-[#f0f0ff] px-4">
        <!-- Logo -->
        <div class="mb-8 text-center">
            <div class="flex items-center justify-center gap-2 mb-2">
                <div class="bg-[#191970] rounded-lg p-2">
                    <i class="fas fa-hospital text-[#D9F855] text-xl"></i>
                </div>
                <span class="text-2xl font-bold text-[#191970]">MedFind</span>
            </div>
            <p class="text-sm text-[#9400D3] font-light">Create your account</p>
        </div>

        <!-- Card -->
        <div class="w-full max-w-sm bg-white rounded-xl border border-[#9400D3]/10 shadow-lg overflow-hidden"
             x-data="{ role: 'consumer' }">
            <div class="p-6">
                <form method="POST" action="{{ route('register') }}" onsubmit="var r=document.querySelector('input[name=role]');if(!r.value)r.value='consumer';">
                    @csrf

                    <!-- Role Selector -->
                    <div class="mb-5">
                        <p class="text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-2">I am a</p>
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Consumer card -->
                            <button type="button"
                                    @click="role = 'consumer'"
                                    :class="role === 'consumer'
                                        ? 'border-[#191970] bg-[#191970] text-[#D9F855]'
                                        : 'border-[#9400D3]/20 bg-[#f8f4ff] text-[#191970] hover:border-[#9400D3]/50'"
                                    class="flex flex-col items-center justify-center gap-1 py-3 rounded-lg border-2 transition text-xs font-medium">
                                <i class="fas fa-user text-base"></i>
                                Consumer
                            </button>
                            <!-- Pharmacy Owner card -->
                            <button type="button"
                                    @click="role = 'pharmacy'"
                                    :class="role === 'pharmacy'
                                        ? 'border-[#191970] bg-[#191970] text-[#D9F855]'
                                        : 'border-[#9400D3]/20 bg-[#f8f4ff] text-[#191970] hover:border-[#9400D3]/50'"
                                    class="flex flex-col items-center justify-center gap-1 py-3 rounded-lg border-2 transition text-xs font-medium">
                                <i class="fas fa-clinic-medical text-base"></i>
                                Pharmacy Owner
                            </button>
                        </div>
                        <!-- Hidden role input — x-model ensures value is always synced -->
                        <input type="hidden" name="role" x-model="role">
                    </div>

                    <!-- Name -->
                    <div class="mb-4">
                        <label for="name" class="block text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                            Full name
                        </label>
                        <input id="name"
                               type="text"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               autofocus
                               autocomplete="name"
                               class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                               placeholder="Full Name">
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

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
                               autocomplete="new-password"
                               class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                               placeholder="••••••••">
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-5">
                        <label for="password_confirmation" class="block text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                            Confirm password
                        </label>
                        <input id="password_confirmation"
                               type="password"
                               name="password_confirmation"
                               required
                               autocomplete="new-password"
                               class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                               placeholder="••••••••">
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                    </div>

                    <!-- Pharmacy Fields (shown only when role = pharmacy) -->
                    <div x-show="role === 'pharmacy'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="mb-5 space-y-4 border-t border-[#9400D3]/10 pt-4">

                        <p class="text-xs font-medium text-[#9400D3] uppercase tracking-wider">Pharmacy details</p>

                        <!-- Pharmacy Name -->
                        <div>
                            <label for="pharmacy_name" class="block text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                                Pharmacy Name <span class="text-red-500">*</span>
                            </label>
                            <input id="pharmacy_name"
                                   type="text"
                                   name="pharmacy_name"
                                   value="{{ old('pharmacy_name') }}"
                                   :required="role === 'pharmacy'"
                                   autocomplete="organization"
                                   class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                                   placeholder="e.g. City Pharmacy">
                            <x-input-error :messages="$errors->get('pharmacy_name')" class="mt-1" />
                        </div>

                        <!-- Address -->
                        <div>
                            <label for="pharmacyAddress" class="block text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                                Address <span class="text-red-500">*</span>
                            </label>
                            <input id="pharmacyAddress"
                                   type="text"
                                   name="pharmacyAddress"
                                   value="{{ old('pharmacyAddress') }}"
                                   :required="role === 'pharmacy'"
                                   class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                                   placeholder="Full pharmacy address">
                            <x-input-error :messages="$errors->get('pharmacyAddress')" class="mt-1" />
                        </div>

                        <!-- Contact Number -->
                        <div>
                            <label for="contactNumber" class="block text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                                Contact Number
                            </label>
                            <input id="contactNumber"
                                   type="text"
                                   name="contactNumber"
                                   value="{{ old('contactNumber') }}"
                                   class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                                   placeholder="e.g. 09XX-XXX-XXXX">
                            <x-input-error :messages="$errors->get('contactNumber')" class="mt-1" />
                        </div>

                        <!-- Lat / Lng -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="latitude" class="block text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                                    Latitude
                                </label>
                                <input id="latitude"
                                       type="number"
                                       name="latitude"
                                       value="{{ old('latitude') }}"
                                       step="any"
                                       class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                                       placeholder="14.5995">
                                <x-input-error :messages="$errors->get('latitude')" class="mt-1" />
                            </div>
                            <div>
                                <label for="longitude" class="block text-xs font-medium text-[#9400D3] uppercase tracking-wider mb-1.5">
                                    Longitude
                                </label>
                                <input id="longitude"
                                       type="number"
                                       name="longitude"
                                       value="{{ old('longitude') }}"
                                       step="any"
                                       class="w-full px-4 py-2.5 bg-[#f8f4ff] border border-[#9400D3]/20 rounded-lg text-sm text-[#191970] focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3] transition"
                                       placeholder="120.9842">
                                <x-input-error :messages="$errors->get('longitude')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-[#191970] text-[#D9F855] text-sm font-medium py-2.5 rounded-lg hover:bg-[#2a2a8a] transition">
                        Create account
                    </button>

                    <!-- Login Link -->
                    <p class="text-center text-sm text-gray-400 font-light mt-4">
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
