@extends('layouts.app')

@section('content')
<div class="flex flex-col max-w-7xl mx-auto" style="height:calc(100vh - 120px);margin-bottom:20px;border-radius:16px;overflow:hidden;" style="background:#1a1a2e;">

    {{-- Chat Header --}}
    <div class="flex items-center gap-3 px-6 py-4 border-b" style="background:#191970;border-color:rgba(148,0,211,0.3);">
        <a href="{{ route('consumer.pharmacy.details', $pharmacy->id) }}" class="text-white hover:text-[#D9F855] transition">
            <i class="fas fa-arrow-left text-lg"></i>
        </a>
        @if($pharmacy->logo_path)
            <img src="{{ asset('storage/' . $pharmacy->logo_path) }}" class="w-10 h-10 rounded-full object-cover border-2 border-[#D9F855]">
        @else
            <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 border-[#D9F855]" style="background:#2a2a5a;">
                <span class="text-[#D9F855] font-bold text-sm">{{ strtoupper(substr($pharmacy->pharmacy_name, 0, 1)) }}</span>
            </div>
        @endif
        <div class="flex-1 min-w-0">
            <h2 class="text-white font-bold text-base truncate">{{ $pharmacy->pharmacy_name }}</h2>
            <p class="text-gray-400 text-xs">{{ $pharmacy->pharmacyAddress }}</p>
        </div>
    </div>

    {{-- Messages Area --}}
    <div id="chatMessages" class="flex-1 overflow-y-auto px-6 py-4 space-y-4" style="background:#1a1a2e;">
        @if($messages->count() === 0)
            <div class="text-center py-12">
                <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style="background:#2a2a5a;">
                    <i class="fas fa-comments text-[#9400D3] text-2xl"></i>
                </div>
                <p class="text-gray-400 text-sm">No messages yet. Start a conversation!</p>
            </div>
        @else
            @foreach($messages as $message)
                {{-- Consumer message (RIGHT) --}}
                <div class="flex justify-end">
                    <div class="max-w-[70%]">
                        <div class="px-4 py-2.5 rounded-2xl rounded-br-sm" style="background:#9400D3;">
                            <p class="text-white text-sm">{{ $message->message }}</p>
                        </div>
                        @if($message->prescription_image)
                            <div class="mt-2">
                                <img src="{{ route('consumer.prescription.view', $message->id) }}"
                                     alt="Prescription"
                                     class="max-w-[200px] w-full rounded-xl border border-white/20 cursor-pointer hover:opacity-90 transition"
                                     onclick="window.open(this.src, '_blank')"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="hidden items-center gap-2 mt-1 px-3 py-1.5 rounded-lg text-xs text-gray-300" style="background:#2a2a5a;">
                                    <i class="fas fa-file-alt"></i> Prescription file attached
                                </div>
                            </div>
                        @endif
                        <p class="text-gray-500 text-xs mt-1 text-right">{{ $message->created_at->format('M d · g:i A') }}</p>
                    </div>
                </div>

                {{-- Pharmacy reply (LEFT) --}}
                @if($message->reply)
                    <div class="flex justify-start">
                        <div class="max-w-[70%]">
                            <div class="px-4 py-2.5 rounded-2xl rounded-bl-sm" style="background:#2a2a5a;">
                                <p class="text-gray-200 text-sm">{{ $message->reply }}</p>
                            </div>
                            <p class="text-gray-500 text-xs mt-1">{{ $pharmacy->pharmacy_name }} · {{ $message->replied_at?->format('M d · g:i A') }}</p>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>

    {{-- Input Bar (Fixed at bottom) --}}
    <div class="px-6 py-4 border-t flex-shrink-0" style="background:#191970;border-color:rgba(148,0,211,0.3);">
        <form method="POST" action="{{ route('consumer.message.send') }}" enctype="multipart/form-data" class="flex items-center gap-3">
            @csrf
            <input type="hidden" name="pharmacy_id" value="{{ $pharmacy->id }}">

            {{-- Attach button --}}
            <label for="chatPrescription" class="cursor-pointer text-gray-400 hover:text-[#D9F855] transition">
                <i class="fas fa-paperclip text-xl"></i>
            </label>
            <input type="file" name="prescription_image" id="chatPrescription" accept=".jpg,.jpeg,.png,.pdf" class="hidden" onchange="showAttached(this)">

            {{-- Message input --}}
            <div class="flex-1 relative">
                <input type="text" name="message" placeholder="Message..." required
                    class="w-full px-5 py-3 rounded-full text-sm text-white outline-none placeholder-gray-400"
                    style="background:#2a2a5a;border:1px solid rgba(148,0,211,0.3);">
                <span id="attachedFile" class="hidden absolute -top-6 left-2 text-xs text-[#D9F855]"></span>
            </div>

            {{-- Send button --}}
            <button type="submit" class="w-11 h-11 rounded-full flex items-center justify-center transition hover:opacity-80 flex-shrink-0" style="background:#9400D3;">
                <i class="fas fa-paper-plane text-white text-sm"></i>
            </button>
        </form>
    </div>
</div>

<script>
    // Auto-scroll to bottom
    var chatBox = document.getElementById('chatMessages');
    if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

    function showAttached(input) {
        var el = document.getElementById('attachedFile');
        if (input.files && input.files[0]) {
            el.textContent = input.files[0].name;
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    }
</script>
@endsection
