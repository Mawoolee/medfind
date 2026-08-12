@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f0f0ff] py-6 px-4 font-sans">
    <div class="max-w-2xl mx-auto">

        <div class="flex items-center justify-between mb-5">
            <h1 class="text-xl font-extrabold text-[#191970]">
                <i class="fas fa-comments mr-2 text-[#9400D3]"></i>My Messages
            </h1>
            <a href="{{ route('consumer.dashboard') }}"
               class="text-xs text-[#9400D3] hover:text-[#191970] font-semibold transition flex items-center gap-1">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-300 text-green-700 text-xs font-semibold px-4 py-3 rounded-xl mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(isset($messages) && $messages->count() > 0)
            <div class="space-y-5">
                @foreach($messages as $pharmacyId => $thread)
                    @php $pharmacy = $thread->first()->pharmacy; @endphp
                    <div class="bg-white rounded-[20px] shadow-sm border border-[#9400D3]/10 overflow-hidden">

                        {{-- Header with delete button --}}
                        <div class="flex items-center gap-3 px-4 py-3 bg-[#f8f4ff] border-b border-[#9400D3]/10">
                            <div class="w-10 h-10 rounded-full bg-[#191970] flex items-center justify-center shrink-0 shadow">
                                <i class="fas fa-store text-[#D9F855] text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-extrabold text-[#191970] text-sm truncate">
                                    {{ $pharmacy->pharmacy_name ?? 'Pharmacy' }}
                                </p>
                                <p class="text-[10px] text-gray-400">{{ $thread->count() }} message{{ $thread->count() > 1 ? 's' : '' }}</p>
                            </div>
                            {{-- Delete conversation button --}}
                            <form method="POST" action="{{ route('consumer.messages.delete', $pharmacyId) }}"
                                  onsubmit="return confirm('Delete this entire conversation?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Delete conversation"
                                    class="w-8 h-8 rounded-full bg-red-50 hover:bg-red-100 text-red-400 hover:text-red-600 flex items-center justify-center transition shrink-0">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            </form>
                        </div>

                        {{-- Chat bubbles --}}
                        <div class="px-4 py-4 space-y-4 max-h-96 overflow-y-auto" id="thread-{{ $pharmacyId }}">
                            @foreach($thread->sortBy('created_at') as $message)
                                {{-- Consumer (RIGHT) --}}
                                <div class="flex justify-end gap-2 items-end">
                                    <div class="max-w-[75%]">
                                        <div class="bg-[#191970] text-[#D9F855] text-xs font-medium px-4 py-2.5 rounded-2xl rounded-br-sm shadow-sm leading-relaxed">
                                            {{ $message->message }}
                                        </div>
                                        <p class="text-[10px] text-gray-400 text-right mt-0.5">
                                            You &middot; {{ $message->created_at->format('M d · g:i A') }}
                                        </p>
                                    </div>
                                    <div class="w-7 h-7 rounded-full bg-[#9400D3]/10 flex items-center justify-center shrink-0">
                                        <i class="fas fa-user text-[#9400D3] text-xs"></i>
                                    </div>
                                </div>

                                {{-- Pharmacy reply (LEFT) --}}
                                @if($message->reply)
                                    <div class="flex justify-start gap-2 items-end">
                                        <div class="w-7 h-7 rounded-full bg-[#191970] flex items-center justify-center shrink-0">
                                            <i class="fas fa-store text-[#D9F855] text-xs"></i>
                                        </div>
                                        <div class="max-w-[75%]">
                                            <div class="bg-[#f8f4ff] border border-[#9400D3]/15 text-[#191970] text-xs font-medium px-4 py-2.5 rounded-2xl rounded-bl-sm shadow-sm leading-relaxed">
                                                {{ $message->reply }}
                                            </div>
                                            <p class="text-[10px] text-gray-400 mt-0.5">
                                                {{ $pharmacy->pharmacy_name ?? 'Pharmacy' }} &middot; {{ $message->replied_at?->format('M d · g:i A') }}
                                            </p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex justify-start gap-2 items-center">
                                        <div class="w-7 h-7 rounded-full bg-[#191970]/10 flex items-center justify-center shrink-0">
                                            <i class="fas fa-store text-[#191970] text-xs"></i>
                                        </div>
                                        <p class="text-[11px] text-gray-400 italic">Waiting for reply...</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- Reply input --}}
                        <div class="px-4 pb-4 pt-1 border-t border-[#9400D3]/08">
                            <form method="POST" action="{{ route('consumer.message.send') }}">
                                @csrf
                                <input type="hidden" name="pharmacy_id" value="{{ $pharmacyId }}">
                                <div class="flex gap-2 items-center bg-[#f8f4ff] rounded-xl px-3 py-2 border border-[#9400D3]/10">
                                    <input type="text" name="message"
                                           placeholder="Send a message to {{ $pharmacy->pharmacy_name ?? 'pharmacy' }}..."
                                           class="flex-1 bg-transparent text-xs text-[#191970] outline-none placeholder-gray-400"
                                           required>
                                    <button type="submit"
                                        class="bg-[#191970] text-[#D9F855] text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-[#2a2a8a] transition shrink-0">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-[20px] shadow-sm border border-[#9400D3]/10 p-12 text-center">
                <div class="w-16 h-16 bg-[#f8f4ff] rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-comments text-[#9400D3] text-2xl"></i>
                </div>
                <h3 class="font-extrabold text-[#191970] mb-1">No messages yet</h3>
                <p class="text-gray-400 text-sm mb-4">Find a pharmacy on the map and send your first message.</p>
                <a href="{{ route('consumer.dashboard') }}"
                   class="inline-block bg-[#191970] text-[#D9F855] font-bold px-5 py-2 rounded-full text-xs hover:bg-[#2a2a8a] transition">
                    Open Map
                </a>
            </div>
        @endif
    </div>
</div>
<script>
    // Auto-scroll each thread to bottom
    document.querySelectorAll('[id^="thread-"]').forEach(function(el) {
        el.scrollTop = el.scrollHeight;
    });
</script>
@endsection