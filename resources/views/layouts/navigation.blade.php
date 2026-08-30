<nav class="bg-white/95 backdrop-blur-sm border-b border-[#9400D3]/10 fixed top-0 left-0 right-0 z-[10000]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            {{-- Logo --}}
            <div class="flex min-w-0 items-center">
                <a href="{{ route('home') }}" class="relative block h-16 w-24 shrink-0 overflow-hidden sm:w-32 lg:w-36" aria-label="MedFind home">
                    <img src="{{ asset('images/Final Logo MedFind.png') }}" alt="MedFind" class="absolute left-0 top-1/2 h-auto w-full max-w-none -translate-y-1/2" width="2000" height="2000">
                </a>
            </div>

            <div class="flex items-center gap-4">
                @auth
                    {{-- Notification Bell --}}
                    @php $notifCount = auth()->user()->unreadNotifications()->count(); @endphp
                    <a href="{{ route('notifications.index') }}"
                       class="relative text-gray-500 hover:text-[#9400D3] transition"
                       title="Notifications">
                        <i class="fas fa-bell text-xl"></i>
                        @if($notifCount > 0)
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                {{ $notifCount > 9 ? '9+' : $notifCount }}
                            </span>
                        @endif
                    </a>

                    {{-- Consumer Dropdown --}}
                    @if(Auth::user()->role === 'consumer')
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                class="text-sm text-[#191970] font-medium hover:text-[#9400D3] transition flex items-center gap-1">
                                <i class="fas fa-user-circle mr-1"></i> Menu
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak @click.away="open = false" @click="open = false"
                                class="absolute right-0 mt-2 w-52 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-[10001]">
                                <a href="{{ route('consumer.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-map-location-dot mr-2 text-[#9400D3]"></i>Map</a>
                                <a href="{{ route('consumer.messages') }}"  class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-comments mr-2 text-[#9400D3]"></i>My Messages</a>
                                <a href="{{ route('consumer.search') }}"    class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-search mr-2 text-[#9400D3]"></i>Search Medicines</a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="{{ route('consumer.profile.settings') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-user-cog mr-2 text-[#9400D3]"></i>Profile Settings</a>
                            </div>
                        </div>
                    @endif

                    {{-- Pharmacy Dropdown --}}
                    @if(in_array(Auth::user()->role, ['pharmacy', 'pharmacy_operator']))
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                class="text-sm text-[#191970] font-medium hover:text-[#9400D3] transition flex items-center gap-1">
                                <i class="fas fa-capsules"></i> Pharmacy
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak @click.away="open = false" @click="open = false"
                                class="absolute right-0 mt-2 w-60 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-[10001]">
                                <a href="{{ route('pharmacy.dashboard') }}"           class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-gauge mr-2 text-[#9400D3]"></i>Dashboard</a>
                                <a href="{{ route('pharmacy.inventory') }}"            class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-box-open mr-2 text-[#9400D3]"></i>Inventory</a>
                                <a href="{{ route('pharmacy.messages') }}"             class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-comments mr-2 text-[#9400D3]"></i>Messages</a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="{{ route('pharmacy.audit-log') }}"            class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-history mr-2 text-[#9400D3]"></i>Audit Log</a>
                                <a href="{{ route('pharmacy.controlled-substances.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-shield-halved mr-2 text-[#9400D3]"></i>Controlled Substances</a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="{{ route('pharmacy.profile.edit') }}"         class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-hospital mr-2 text-[#9400D3]"></i>Pharmacy Profile</a>
                            </div>
                        </div>
                    @endif

                    {{-- Admin Dropdown --}}
                    @if(Auth::user()->role === 'admin')
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                class="text-sm text-[#191970] font-medium hover:text-[#9400D3] transition flex items-center gap-1">
                                <i class="fas fa-shield-halved"></i> Admin
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak @click.away="open = false" @click="open = false"
                                class="absolute right-0 mt-2 w-60 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-[10001]">
                                <a href="{{ route('admin.dashboard') }}"  class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-gauge mr-2 text-[#9400D3]"></i>Dashboard</a>
                                <a href="{{ route('admin.users') }}"       class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-users mr-2 text-[#9400D3]"></i>Users</a>
                                <a href="{{ route('admin.pharmacies') }}"  class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-store mr-2 text-[#9400D3]"></i>Pharmacies</a>
                                <a href="{{ route('admin.requirements') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-file-circle-check mr-2 text-[#9400D3]"></i>Requirements</a>
                            </div>
                        </div>
                    @endif

                    <span class="text-sm text-[#191970] font-medium">{{ Auth::user()->name }}</span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-[#9400D3] hover:text-[#191970] font-medium transition">
                            Log out
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-sm text-[#191970] font-medium hover:text-[#9400D3] transition">
                        Log in
                    </a>
                    <a href="{{ route('register') }}" class="text-sm bg-[#191970] text-[#D9F855] px-4 py-2 rounded-full font-semibold hover:bg-[#2a2a8a] transition">
                        Sign up
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>
