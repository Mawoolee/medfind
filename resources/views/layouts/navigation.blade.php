<nav class="bg-white/95 backdrop-blur-sm border-b border-[#9400D3]/10 fixed top-0 left-0 right-0 z-[10000]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <span class="text-2xl font-extrabold text-[#191970]">Med<span class="text-[#9400D3]">Find</span></span>
                </a>
            </div>

            <div class="flex items-center gap-4">
                @auth
                    @if(in_array(Auth::user()->role, ['pharmacy', 'pharmacy_operator']))
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="text-sm text-[#191970] font-medium hover:text-[#9400D3] transition flex items-center gap-1">
                                <i class="fas fa-capsules"></i> Pharmacy
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
<div x-show="open" @click.away="open = false" @click="open = false" class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-[10001]">
<a href="{{ route('pharmacy.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-gauge mr-2 text-[#9400D3]"></i>Dashboard</a>
                                <a href="{{ route('pharmacy.inventory') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-box-open mr-2 text-[#9400D3]"></i>Inventory</a>
                                <a href="{{ route('pharmacy.messages') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-comments mr-2 text-[#9400D3]"></i>Messages</a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="{{ route('pharmacy.profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-[#9400D3]/5"><i class="fas fa-hospital mr-2 text-[#9400D3]"></i>Pharmacy Profile</a>
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