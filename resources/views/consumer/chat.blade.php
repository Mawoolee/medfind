@extends('layouts.app')

@section('content')
<div class="flex flex-col max-w-7xl mx-auto" style="height:calc(100vh - 120px);margin-bottom:20px;border-radius:16px;overflow:hidden;" style="background:#1a1a2e;">

    {{-- Chat Header --}}
    <div class="flex items-center gap-3 px-4 sm:px-6 py-4 border-b" style="background:#191970;border-color:rgba(148,0,211,0.3);">
        <a href="{{ route('consumer.pharmacy.details', $pharmacy->id) }}" class="text-white hover:text-[#D9F855] transition">
            <i class="fas fa-arrow-left text-lg"></i>
        </a>
        @if($pharmacy->logo_path)
            <img src="{{ $pharmacy->logo_url }}" class="w-10 h-10 rounded-full object-cover border-2 border-[#D9F855]">
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
    <div id="chatMessages" class="flex-1 overflow-y-auto px-4 sm:px-6 py-4 space-y-4" style="background:#1a1a2e;">
        @if($messages->count() === 0)
            <div class="text-center py-12">
                <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style="background:#2a2a5a;">
                    <i class="fas fa-comments text-[#9400D3] text-2xl"></i>
                </div>
                <p class="text-gray-400 text-sm">No messages yet. Start a conversation!</p>
            </div>
        @else
            @foreach($messages as $message)
                @php $msgSender = $message->sender ?? 'consumer'; @endphp

                @if($msgSender === 'consumer')
                    {{-- Consumer message (RIGHT - I sent it) --}}
                    <div class="flex justify-end">
                        <div class="max-w-[70%]">
                            <div class="px-4 py-2.5 rounded-2xl rounded-br-sm" style="background:#9400D3;">
                                <p class="text-white text-sm">{{ $message->message }}</p>
                            </div>
                            @if($message->prescription_image)
                                <div class="mt-2 flex justify-end">
                                    <img src="{{ route('consumer.prescription.view', $message->id) }}"
                                         alt="Prescription"
                                         class="max-w-[200px] w-full rounded-xl border border-white/20 cursor-pointer hover:opacity-90 transition"
                                         onclick="window.open(this.src, '_blank')">
                                </div>
                            @endif
                            @if($message->attachments && count($message->attachments) > 0)
                                <div class="mt-2 flex flex-wrap gap-1 justify-end">
                                    @foreach($message->attachments as $idx => $att)
                                        @php
                                            $mime = is_array($att) ? ($att['mime'] ?? '') : '';
                                            $name = is_array($att) ? ($att['name'] ?? 'attachment') : 'attachment';
                                            $isImage = str_starts_with($mime, 'image/');
                                        @endphp
                                        @if($isImage)
                                            <img src="{{ route('consumer.attachment.view', [$message->id, $idx]) }}"
                                                 class="w-16 h-16 rounded-lg object-cover border border-white/20 cursor-pointer hover:opacity-80"
                                                 onclick="window.open(this.src, '_blank')">
                                        @else
                                            <a href="{{ route('consumer.attachment.view', [$message->id, $idx]) }}" target="_blank"
                                               class="flex items-center gap-2 px-3 py-2 rounded-lg border border-white/20 hover:bg-white/5 transition" style="background:#2a2a5a;">
                                                <i class="fas fa-file-alt text-[#D9F855] text-sm"></i>
                                                <span class="text-white text-xs truncate max-w-[120px]">{{ $name }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            <p class="text-gray-500 text-xs mt-1 text-right">You · {{ $message->created_at->format('M d · g:i A') }}</p>
                        </div>
                    </div>
                @else
                    {{-- Pharmacy reply (LEFT) --}}
                    <div class="flex justify-start">
                        <div class="max-w-[70%]">
                            <div class="px-4 py-2.5 rounded-2xl rounded-bl-sm" style="background:#2a2a5a;">
                                <p class="text-gray-200 text-sm">{{ $message->message }}</p>
                            </div>
                            <p class="text-gray-500 text-xs mt-1">{{ $pharmacy->pharmacy_name }} · {{ $message->created_at->format('M d · g:i A') }}</p>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>

    {{-- Input Bar (Fixed at bottom) --}}
    <div class="px-4 sm:px-6 py-4 border-t flex-shrink-0" style="background:#191970;border-color:rgba(148,0,211,0.3);">
        <form id="chatForm" method="POST" action="{{ route('consumer.message.send') }}" enctype="multipart/form-data" class="flex items-center gap-3">
            @csrf
            <input type="hidden" name="pharmacy_id" value="{{ $pharmacy->id }}">

            {{-- Attach button --}}
            <label for="chatPrescription" class="cursor-pointer text-gray-400 hover:text-[#D9F855] transition">
                <i class="fas fa-paperclip text-xl"></i>
            </label>
            <input type="file" name="attachments[]" id="chatPrescription" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv" class="hidden" multiple>

            {{-- Message input --}}
            <div class="flex-1 relative">
                <input type="text" name="message" placeholder="Message..." required autocomplete="off"
                    class="w-full px-5 py-3 rounded-full text-base text-white outline-none placeholder-gray-400"
                    style="background:#2a2a5a;border:1px solid rgba(148,0,211,0.3);">
            </div>

            {{-- Send button --}}
            <button type="submit" class="w-11 h-11 rounded-full flex items-center justify-center transition hover:opacity-80 flex-shrink-0" style="background:#9400D3;">
                <i class="fas fa-paper-plane text-white text-sm"></i>
            </button>
        </form>
    </div>
</div>

{{-- File Preview Popup --}}
<div id="filePreviewPopup" class="fixed inset-0 z-[99999] hidden items-end justify-center pb-20" style="background:rgba(0,0,0,0.5);" onclick="if(event.target===this)closeFilePreview()">
    <div class="bg-[#191970] rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden border border-[#9400D3]/30">
        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-white/10">
            <span id="filePreviewTitle" class="text-white font-bold text-sm">Send files</span>
            <div class="flex items-center gap-2">
                <label for="addMoreFiles" class="cursor-pointer text-gray-400 hover:text-[#D9F855] transition" title="Add more files">
                    <i class="fas fa-plus"></i>
                </label>
                <input type="file" id="addMoreFiles" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv" multiple class="hidden" onchange="addMoreFilesToPreview(this)">
                <button onclick="closeFilePreview()" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        {{-- File list --}}
        <div id="filePreviewList" class="max-h-64 overflow-y-auto px-5 py-3 space-y-2"></div>
        {{-- Footer with caption + send --}}
        <div class="px-5 py-3 border-t border-white/10 flex items-center gap-3">
            <input type="text" id="fileCaption" placeholder="Add a caption..."
                   class="flex-1 px-4 py-2 rounded-full text-base text-white outline-none placeholder-gray-400"
                   style="background:#2a2a5a;border:1px solid rgba(148,0,211,0.3);"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();sendWithFiles();}">
            <button onclick="sendWithFiles()" class="w-10 h-10 rounded-full flex items-center justify-center transition hover:opacity-80" style="background:#9400D3;">
                <i class="fas fa-paper-plane text-white text-sm"></i>
            </button>
        </div>
    </div>
</div>

<script>
    // Auto-scroll to bottom
    var chatBox = document.getElementById('chatMessages');
    if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

    var pharmacyId = {{ $pharmacy->id }};
    var skipPollUntil = 0;

    // File preview popup
    var pendingFiles = [];

    function showFilePreview(files) {
        pendingFiles = Array.from(files);
        var popup = document.getElementById('filePreviewPopup');
        var list = document.getElementById('filePreviewList');
        var title = document.getElementById('filePreviewTitle');

        title.textContent = 'Send ' + pendingFiles.length + ' file' + (pendingFiles.length > 1 ? 's' : '');

        list.innerHTML = '';
        pendingFiles.forEach(function(file, idx) {
            var size = file.size < 1024*1024 ? (file.size/1024).toFixed(1) + ' KB' : (file.size/1024/1024).toFixed(1) + ' MB';
            var isImage = file.type && file.type.startsWith('image/');
            var row = document.createElement('div');
            row.className = 'flex items-center gap-3 py-2 border-b border-white/5';
            var thumbId = 'chatFileThumb_' + idx;
            row.innerHTML = '<div id="' + thumbId + '" class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden" style="background:#9400D3;">'
                + '<i class="fas fa-file text-white text-sm"></i></div>'
                + '<div class="flex-1 min-w-0"><p class="text-white text-xs font-medium truncate">' + file.name + '</p>'
                + '<p class="text-gray-400 text-xs">' + size + '</p></div>'
                + '<button onclick="removeFileFromPreview(' + idx + ')" class="text-gray-500 hover:text-red-400 transition"><i class="fas fa-times"></i></button>';
            list.appendChild(row);

            // Show image thumbnail
            if (isImage) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var thumbEl = document.getElementById(thumbId);
                    if (thumbEl) {
                        thumbEl.innerHTML = '<img src="' + e.target.result + '" class="w-10 h-10 object-cover rounded-lg">';
                    }
                };
                reader.readAsDataURL(file);
            }
        });

        popup.style.display = 'flex';
        popup.classList.remove('hidden');
        document.getElementById('fileCaption').focus();
    }

    function addMoreFilesToPreview(input) {
        if (input.files) {
            for (var i = 0; i < input.files.length; i++) {
                if (pendingFiles.length < 10) pendingFiles.push(input.files[i]);
            }
            showFilePreview(pendingFiles);
        }
        input.value = '';
    }

    function removeFileFromPreview(idx) {
        pendingFiles.splice(idx, 1);
        if (pendingFiles.length === 0) { closeFilePreview(); return; }
        showFilePreview(pendingFiles);
    }

    function closeFilePreview() {
        var popup = document.getElementById('filePreviewPopup');
        popup.style.display = 'none';
        popup.classList.add('hidden');
        pendingFiles = [];
    }

    function sendWithFiles() {
        if (pendingFiles.length === 0) return;
        var caption = document.getElementById('fileCaption').value.trim() || '';
        var form = document.getElementById('chatForm');

        var fd = new FormData(form);
        fd.set('message', caption || 'Sent ' + pendingFiles.length + ' file(s)');
        fd.delete('attachments[]');
        pendingFiles.forEach(function(file) { fd.append('attachments[]', file); });

        // Show bubble immediately with image previews
        var msgsDiv = document.getElementById('chatMessages');
        var bubble = document.createElement('div');
        bubble.className = 'flex justify-end';
        var captionHtml = caption ? '<div class="px-4 py-2.5 rounded-2xl rounded-br-sm" style="background:#9400D3;"><p class="text-white text-sm">' + caption + '</p></div>' : '';
        var thumbsHtml = '<div class="mt-1 flex flex-wrap gap-1 justify-end">';
        pendingFiles.forEach(function(file) {
            var isImage = file.type && file.type.startsWith('image/');
            if (isImage) {
                var url = URL.createObjectURL(file);
                thumbsHtml += '<img src="' + url + '" class="w-16 h-16 rounded-lg object-cover border border-white/20">';
            } else {
                thumbsHtml += '<a class="flex items-center gap-2 px-3 py-2 rounded-lg border border-white/20" style="background:#2a2a5a;"><i class="fas fa-file-alt text-[#D9F855] text-sm"></i><span class="text-white text-xs truncate max-w-[120px]">' + file.name + '</span></a>';
            }
        });
        thumbsHtml += '</div>';
        bubble.innerHTML = '<div class="max-w-[70%]">' + captionHtml + thumbsHtml + '<p class="text-gray-500 text-xs mt-1 text-right">You · just now</p></div>';
        msgsDiv.appendChild(bubble);
        msgsDiv.scrollTop = msgsDiv.scrollHeight;

        skipPollUntil = Date.now() + 5000;

        fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        closeFilePreview();
    }

    // Show popup when files are selected
    document.getElementById('chatPrescription').addEventListener('change', function(e) {
        if (e.target.files && e.target.files.length > 0) {
            showFilePreview(e.target.files);
            e.target.value = '';
        }
    });

    // Send message via AJAX with optimistic UI
    function sendMessage(e) {
        e.preventDefault();
        var form = document.getElementById('chatForm');
        var input = form.querySelector('input[name="message"]');
        var msg = input.value.trim();
        if (!msg) return;

        var fd = new FormData(form);

        // Show bubble immediately
        var msgsDiv = document.getElementById('chatMessages');
        var bubble = document.createElement('div');
        bubble.className = 'flex justify-end';
        bubble.innerHTML = '<div class="max-w-[70%]"><div class="px-4 py-2.5 rounded-2xl rounded-br-sm" style="background:#9400D3;"><p class="text-white text-sm">' + msg + '</p></div><p class="text-gray-500 text-xs mt-1 text-right">You · just now</p></div>';
        msgsDiv.appendChild(bubble);
        msgsDiv.scrollTop = msgsDiv.scrollHeight;
        input.value = '';

        skipPollUntil = Date.now() + 5000;

        fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    document.getElementById('chatForm').addEventListener('submit', sendMessage);

    // Auto-refresh chat every 3 seconds (polling)
    setInterval(function() {
        if (Date.now() < skipPollUntil) return;
        fetch('/consumer/messages/data', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var conv = data.find(function(c) { return c.pharmacy_id == pharmacyId; });
                if (!conv) return;
                var chatMsgs = document.getElementById('chatMessages');
                if (!chatMsgs) return;

                var items = [];
                conv.messages.forEach(function(m) {
                    items.push({ type: m.sender || 'consumer', text: m.message, time: m.created_at, id: m.id, attachmentCount: m.attachment_count || 0, attachmentsMeta: m.attachments_meta || [] });
                });
                items.sort(function(a, b) { return new Date(a.time) - new Date(b.time); });

                var html = '';
                items.forEach(function(item) {
                    var dt = new Date(item.time);
                    var timeStr = dt.toLocaleDateString('en-US', {month:'short',day:'numeric'}) + ' · ' + dt.toLocaleTimeString('en-US', {hour:'numeric',minute:'2-digit'});
                    if (item.type === 'consumer') {
                        html += '<div class="flex justify-end"><div class="max-w-[70%]"><div class="px-4 py-2.5 rounded-2xl rounded-br-sm" style="background:#9400D3;"><p class="text-white text-sm">' + item.text + '</p></div>';
                        if (item.attachmentCount > 0) {
                            html += '<div class="mt-1 flex flex-wrap gap-1 justify-end">';
                            item.attachmentsMeta.forEach(function(att) {
                                if (att.mime && att.mime.startsWith('image/')) {
                                    html += '<img src="/consumer/attachment/' + item.id + '/' + att.index + '" class="w-16 h-16 rounded-lg object-cover border border-white/20 cursor-pointer" onclick="window.open(this.src, \'_blank\')">';
                                } else {
                                    html += '<a href="/consumer/attachment/' + item.id + '/' + att.index + '" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-white/20 hover:bg-white/5 transition" style="background:#2a2a5a;"><i class="fas fa-file-alt text-[#D9F855] text-sm"></i><span class="text-white text-xs truncate max-w-[120px]">' + att.name + '</span></a>';
                                }
                            });
                            html += '</div>';
                        }
                        html += '<p class="text-gray-500 text-xs mt-1 text-right">You · ' + timeStr + '</p></div></div>';
                    } else {
                        html += '<div class="flex justify-start"><div class="max-w-[70%]"><div class="px-4 py-2.5 rounded-2xl rounded-bl-sm" style="background:#2a2a5a;"><p class="text-gray-200 text-sm">' + item.text + '</p></div>';
                        if (item.attachmentCount > 0) {
                            html += '<div class="mt-1 flex flex-wrap gap-1">';
                            item.attachmentsMeta.forEach(function(att) {
                                if (att.mime && att.mime.startsWith('image/')) {
                                    html += '<img src="/consumer/attachment/' + item.id + '/' + att.index + '" class="w-16 h-16 rounded-lg object-cover border border-white/20 cursor-pointer" onclick="window.open(this.src, \'_blank\')">';
                                } else {
                                    html += '<a href="/consumer/attachment/' + item.id + '/' + att.index + '" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-white/20 hover:bg-white/5 transition" style="background:#2a2a5a;"><i class="fas fa-file-alt text-[#D9F855] text-sm"></i><span class="text-white text-xs truncate max-w-[120px]">' + att.name + '</span></a>';
                                }
                            });
                            html += '</div>';
                        }
                        html += '<p class="text-gray-500 text-xs mt-1">{{ $pharmacy->pharmacy_name }} · ' + timeStr + '</p></div></div>';
                    }
                });
                chatMsgs.innerHTML = html;
                chatMsgs.scrollTop = chatMsgs.scrollHeight;
            }).catch(function() {});
    }, 3000);

    // Real-time: listen for pharmacy replies via WebSocket
    if (window.Echo) {
        window.Echo.channel('consumer.{{ auth()->id() }}')
            .listen('.message.sent', function(e) {
                if (e.direction !== 'pharmacy_to_consumer') return;
                if (e.pharmacyId != pharmacyId) return;
                var msgsDiv = document.getElementById('chatMessages');
                if (!msgsDiv) return;
                var bubble = document.createElement('div');
                bubble.className = 'flex justify-start';
                bubble.innerHTML = '<div class="max-w-[70%]"><div class="px-4 py-2.5 rounded-2xl rounded-bl-sm" style="background:#2a2a5a;"><p class="text-gray-200 text-sm">' + (e.reply || e.message) + '</p></div><p class="text-gray-500 text-xs mt-1">{{ $pharmacy->pharmacy_name }} · just now</p></div>';
                msgsDiv.appendChild(bubble);
                msgsDiv.scrollTop = msgsDiv.scrollHeight;
            });
    }
</script>
@endsection
