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