@extends('layouts.app')

@section('title', 'Pharmacy Messages')

@section('content')
<div class="flex" style="height:calc(100vh - 120px);margin-top:10px;margin-bottom:20px;max-width:1280px;margin-left:auto;margin-right:auto;border-radius:16px;overflow:hidden;">

    {{-- LEFT PANEL --}}
    <div class="w-96 flex-shrink-0 border-r flex flex-col" style="background:#191970;border-color:rgba(148,0,211,0.3);">
        <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:rgba(148,0,211,0.3);">
            <h2 class="text-white font-bold text-lg">Messages</h2>
            <x-back-button :href="route('pharmacy.dashboard')" label="Back to Pharmacy Dashboard" class="shrink-0 bg-[#191970] hover:!bg-white" />
        </div>
        <div class="flex-1 overflow-y-auto">
            @php $grouped = $messages->groupBy('consumer_id'); @endphp
            @forelse($grouped as $consumerId => $thread)
                @php
                    $consumer = $thread->first()->consumer;
                    $lastMsg = $thread->sortByDesc('created_at')->first();
                    $unread = $thread->where('is_read', false)->count();
                    $lastText = $lastMsg->sender === 'pharmacy' ? 'You: ' . Str::limit($lastMsg->message, 25) : Str::limit($lastMsg->message, 30);
                @endphp
                <div class="conversation-item relative group" x-data="{ menuOpen: false }" data-consumer-id="{{ $consumerId }}">
                    <div class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-white/5 transition border-b border-white/5" onclick="openConversation({{ $consumerId }})">
                        <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 border-2 border-[#D9F855]" style="background:#2a2a5a;">
                            <span class="text-[#D9F855] font-bold text-sm">{{ strtoupper(substr($consumer->name ?? 'C', 0, 1)) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-white font-semibold text-sm truncate">{{ $consumer->name ?? 'Customer' }}</p>
                            </div>
                            <p class="text-gray-400 text-xs truncate mt-0.5">{{ $lastText }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-gray-400 text-xs">{{ $lastMsg->created_at->format('M d') }}</span>
                            @if($unread > 0)
                                <span class="unread-badge bg-[#9400D3] text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">{{ $unread }}</span>
                            @endif
                            <button @click.stop="menuOpen = !menuOpen" class="text-gray-500 hover:text-white opacity-0 group-hover:opacity-100 transition p-1">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                    </div>
                    <div x-show="menuOpen" @click.away="menuOpen = false" x-cloak class="absolute top-10 right-3 bg-white rounded-lg shadow-lg py-1 z-50 w-44">
                        <form method="POST" action="{{ route('pharmacy.message.delete', $consumerId) }}" onsubmit="return confirm('Delete this conversation?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                                <i class="fas fa-trash-alt mr-2"></i>Delete conversation
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <i class="fas fa-inbox text-gray-500 text-3xl mb-3 block"></i>
                    <p class="text-gray-400 text-sm">No messages yet</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- RIGHT PANEL --}}
    <div class="flex-1 flex flex-col" style="background:#1a1a2e;">
        <div id="chatEmpty" class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <i class="fas fa-comments text-gray-600 text-4xl mb-3 block"></i>
                <p class="text-gray-500 text-sm">Select a conversation to start</p>
            </div>
        </div>

        @foreach($grouped as $consumerId => $thread)
            @php
                $consumer = $thread->first()->consumer;
                $lastUnreplied = $thread->whereNull('reply')->sortByDesc('created_at')->first();
                $threadLastMsg = $thread->sortByDesc('created_at')->first();
            @endphp
            <div class="chat-view hidden flex-col h-full" id="chat-{{ $consumerId }}">
                <div class="flex items-center gap-3 px-6 py-3 border-b flex-shrink-0" style="background:#191970;border-color:rgba(148,0,211,0.3);">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center border-2 border-[#D9F855]" style="background:#2a2a5a;">
                        <span class="text-[#D9F855] font-bold text-xs">{{ strtoupper(substr($consumer->name ?? 'C', 0, 1)) }}</span>
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-sm">{{ $consumer->name ?? 'Customer' }}</h3>
                        <p class="text-gray-400 text-xs">{{ $consumer->email ?? '' }}</p>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3 chat-messages">
                    @php
                        // Build flat timeline using sender field
                        $timeline = $thread->sortBy('created_at')->map(function($msg) {
                            $attachments = $msg->attachments && is_array($msg->attachments) ? $msg->attachments : [];
                            return (object)[
                                'type' => $msg->sender ?? 'consumer',
                                'text' => $msg->message,
                                'time' => $msg->created_at,
                                'id' => $msg->id,
                                'has_prescription' => !empty($msg->prescription_image),
                                'has_attachments' => count($attachments) > 0,
                                'attachment_count' => count($attachments),
                                'attachments' => $attachments,
                            ];
                        });
                    @endphp

                    @foreach($timeline as $item)
                        @if($item->type === 'consumer')
                            {{-- Consumer message (LEFT) --}}
                            <div class="flex justify-start">
                                <div class="max-w-[70%]">
                                    <div class="px-4 py-2.5 rounded-2xl rounded-bl-sm" style="background:#2a2a5a;">
                                        <p class="text-gray-200 text-sm">{{ $item->text }}</p>
                                    </div>
                                    @if($item->has_prescription)
                                        <div class="mt-2">
                                            <img src="{{ route('pharmacy.prescription.serve', $item->id) }}" alt="Prescription" class="max-w-[180px] rounded-xl border border-white/20 cursor-pointer hover:opacity-90" onclick="window.open(this.src, '_blank')">
                                        </div>
                                    @endif
                                    @if($item->has_attachments)
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            @foreach($item->attachments as $idx => $att)
                                                @php
                                                    $mime = is_array($att) ? ($att['mime'] ?? '') : '';
                                                    $name = is_array($att) ? ($att['name'] ?? 'attachment') : 'attachment';
                                                    $isImage = str_starts_with($mime, 'image/');
                                                @endphp
                                                @if($isImage)
                                                    <img src="{{ route('pharmacy.attachment.view', [$item->id, $idx]) }}"
                                                         class="w-16 h-16 rounded-lg object-cover border border-white/20 cursor-pointer hover:opacity-80"
                                                         onclick="window.open(this.src, '_blank')">
                                                @else
                                                    <a href="{{ route('pharmacy.attachment.view', [$item->id, $idx]) }}" target="_blank"
                                                       class="flex items-center gap-2 px-3 py-2 rounded-lg border border-white/20 hover:bg-white/5 transition" style="background:#2a2a5a;">
                                                        <i class="fas fa-file-alt text-[#D9F855] text-sm"></i>
                                                        <span class="text-white text-xs truncate max-w-[120px]">{{ $name }}</span>
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                    <p class="text-gray-500 text-xs mt-1">{{ $consumer->name ?? 'Customer' }} · {{ $item->time->format('M d · g:i A') }}</p>
                                </div>
                            </div>
                        @else
                            {{-- Pharmacy reply (RIGHT) --}}
                            <div class="flex justify-end">
                                <div class="max-w-[70%]">
                                    <div class="px-4 py-2.5 rounded-2xl rounded-br-sm" style="background:#9400D3;">
                                        <p class="text-white text-sm">{{ $item->text }}</p>
                                    </div>
                                    <p class="text-gray-500 text-xs mt-1 text-right">You · {{ $item->time->format('M d · g:i A') }}</p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="px-6 py-3 border-t flex-shrink-0" style="background:#191970;border-color:rgba(148,0,211,0.3);">
                    <form method="POST" action="{{ route('pharmacy.message.reply', $threadLastMsg->id) }}" class="flex items-center gap-3" enctype="multipart/form-data" onsubmit="return sendReply(this, {{ $consumerId }})">
                            @csrf
                            <label for="phRx_{{ $consumerId }}" class="cursor-pointer text-gray-400 hover:text-[#D9F855] transition flex-shrink-0">
                                <i class="fas fa-paperclip text-lg"></i>
                            </label>
                            <input type="file" name="attachments[]" id="phRx_{{ $consumerId }}" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv" class="hidden" multiple>
                            <input type="text" name="reply" placeholder="Reply..." required autocomplete="off" class="flex-1 px-5 py-3 rounded-full text-sm text-white outline-none placeholder-gray-400" style="background:#2a2a5a;border:1px solid rgba(148,0,211,0.3);">
                            <button type="submit" class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 transition hover:opacity-80" style="background:#9400D3;">
                                <i class="fas fa-paper-plane text-white text-sm"></i>
                            </button>
                        </form>

                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
var activeConsumerId = null;
var skipPollUntil = 0;

function openConversation(consumerId) {
    activeConsumerId = consumerId;
    window.location.hash = 'chat-' + consumerId;
    document.getElementById('chatEmpty').style.display = 'none';
    document.querySelectorAll('.chat-view').forEach(function(el) {
        el.classList.add('hidden');
        el.classList.remove('flex');
    });
    var chat = document.getElementById('chat-' + consumerId);
    if (chat) {
        chat.classList.remove('hidden');
        chat.classList.add('flex');
        var msgs = chat.querySelector('.chat-messages');
        if (msgs) msgs.scrollTop = msgs.scrollHeight;
        var input = chat.querySelector('input[name="reply"]');
        if (input) input.focus();
    }
    document.querySelectorAll('.conversation-item').forEach(function(el) {
        el.classList.remove('active-conv');
    });
    var item = document.querySelector('[data-consumer-id="' + consumerId + '"]');
    if (item) item.classList.add('active-conv');

    // Mark all unread messages in this conversation as read
    fetch('/pharmacy/message/mark-conversation-read/' + consumerId, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
            // Remove the unread badge from this conversation in the left panel
            if (item) {
                var badge = item.querySelector('.unread-badge');
                if (badge) badge.remove();
            }
        }
    }).catch(function() {});
}

function sendReply(form, consumerId) {
    var input = form.querySelector('input[name="reply"]');
    var msg = input.value.trim();
    if (!msg) return false;

    var fd = new FormData(form);
    var msgsDiv = document.getElementById('chat-' + consumerId).querySelector('.chat-messages');
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
    }).then(function() {
        var item = document.querySelector('[data-consumer-id="' + consumerId + '"]');
        if (item) {
            var preview = item.querySelector('p.text-gray-400.text-xs');
            if (preview) preview.textContent = 'You: ' + msg.substring(0, 25);
        }
    });
    return false;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        var el = e.target;
        if (el.tagName === 'INPUT' && el.name === 'reply' && el.closest('.chat-view')) {
            e.preventDefault();
            var form = el.closest('form');
            sendReply(form, activeConsumerId);
        }
    }
});


// Restore active conversation from URL hash on page load
(function() {
    var hash = window.location.hash;
    if (hash && hash.startsWith('#chat-')) {
        var id = hash.replace('#chat-', '');
        if (id) setTimeout(function() { openConversation(parseInt(id)); }, 100);
    }
})();
// Auto-refresh chat every 3 seconds
setInterval(function() {
    if (!activeConsumerId) return;
    if (Date.now() < skipPollUntil) return;
    fetch('/pharmacy/messages-data', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var conv = data.find(function(c) { return c.consumer_id == activeConsumerId; });
            if (!conv) return;
            var chat = document.getElementById('chat-' + activeConsumerId);
            if (!chat) return;
            var chatMsgs = chat.querySelector('.chat-messages');
            if (!chatMsgs) return;

            // Build flat timeline using sender field
            var items = [];
            conv.messages.forEach(function(m) {
                items.push({ type: m.sender || 'consumer', text: m.message, time: m.created_at, hasPrescription: m.has_prescription || false, id: m.id, attachmentCount: m.attachment_count || 0, attachmentsMeta: m.attachments_meta || [] });
            });
            items.sort(function(a, b) { return new Date(a.time) - new Date(b.time); });

            // Always rebuild from server data (server is source of truth)

            var html = '';
            items.forEach(function(item) {
                var dt = new Date(item.time);
                var timeStr = dt.toLocaleDateString('en-US', {month:'short',day:'numeric'}) + ' · ' + dt.toLocaleTimeString('en-US', {hour:'numeric',minute:'2-digit'});
                if (item.type === 'consumer') {
                    html += '<div class="flex justify-start"><div class="max-w-[70%]"><div class="px-4 py-2.5 rounded-2xl rounded-bl-sm" style="background:#2a2a5a;"><p class="text-gray-200 text-sm">' + item.text + '</p></div>';
                    if (item.attachmentCount > 0) {
                        html += '<div class="mt-1 flex flex-wrap gap-1">';
                        item.attachmentsMeta.forEach(function(att) {
                            if (att.mime && att.mime.startsWith('image/')) {
                                html += '<img src="/pharmacy/attachment/' + item.id + '/' + att.index + '" class="w-16 h-16 rounded-lg object-cover border border-white/20 cursor-pointer" onclick="window.open(this.src, \'_blank\')">';
                            } else {
                                html += '<a href="/pharmacy/attachment/' + item.id + '/' + att.index + '" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-white/20 hover:bg-white/5 transition" style="background:#2a2a5a;"><i class="fas fa-file-alt text-[#D9F855] text-sm"></i><span class="text-white text-xs truncate max-w-[120px]">' + att.name + '</span></a>';
                            }
                        });
                        html += '</div>';
                    }
                    html += '<p class="text-gray-500 text-xs mt-1">' + conv.consumer_name + ' · ' + timeStr + '</p></div></div>';
                } else {
                    html += '<div class="flex justify-end"><div class="max-w-[70%]"><div class="px-4 py-2.5 rounded-2xl rounded-br-sm" style="background:#9400D3;"><p class="text-white text-sm">' + item.text + '</p></div>';
                    if (item.attachmentCount > 0) {
                        html += '<div class="mt-1 flex flex-wrap gap-1 justify-end">';
                        item.attachmentsMeta.forEach(function(att) {
                            if (att.mime && att.mime.startsWith('image/')) {
                                html += '<img src="/pharmacy/attachment/' + item.id + '/' + att.index + '" class="w-16 h-16 rounded-lg object-cover border border-white/20 cursor-pointer" onclick="window.open(this.src, \'_blank\')">';
                            } else {
                                html += '<a href="/pharmacy/attachment/' + item.id + '/' + att.index + '" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-white/20 hover:bg-white/5 transition" style="background:#2a2a5a;"><i class="fas fa-file-alt text-[#D9F855] text-sm"></i><span class="text-white text-xs truncate max-w-[120px]">' + att.name + '</span></a>';
                            }
                        });
                        html += '</div>';
                    }
                    html += '<p class="text-gray-500 text-xs mt-1 text-right">You · ' + timeStr + '</p></div></div>';
                }
            });
            chatMsgs.innerHTML = html;
            chatMsgs.scrollTop = chatMsgs.scrollHeight;
        }).catch(function() {});
}, 3000);

// Real-time: listen for consumer messages via WebSocket
if (window.Echo) {
    window.Echo.channel('pharmacy.{{ $pharmacy->id }}')
        .listen('.message.sent', function(e) {
            if (e.direction !== 'consumer_to_pharmacy') return;
            // If this conversation is open, add the message bubble
            if (activeConsumerId && e.consumerId == activeConsumerId) {
                var msgsDiv = document.getElementById('chat-' + activeConsumerId);
                if (!msgsDiv) return;
                var chatMsgs = msgsDiv.querySelector('.chat-messages');
                if (!chatMsgs) return;
                var bubble = document.createElement('div');
                bubble.className = 'flex justify-start';
                bubble.innerHTML = '<div class="max-w-[70%]"><div class="px-4 py-2.5 rounded-2xl rounded-bl-sm" style="background:#2a2a5a;"><p class="text-gray-200 text-sm">' + e.message + '</p></div><p class="text-gray-500 text-xs mt-1">' + (e.consumerName || 'Customer') + ' · just now</p></div>';
                chatMsgs.appendChild(bubble);
                chatMsgs.scrollTop = chatMsgs.scrollHeight;
            }
            // Update unread badge on left panel
            var item = document.querySelector('[data-consumer-id="' + e.consumerId + '"]');
            if (item) {
                var badge = item.querySelector('.bg-\\[\\#9400D3\\]');
                if (badge) {
                    badge.textContent = parseInt(badge.textContent || '0') + 1;
                } else {
                    var row = item.querySelector('.flex.items-center.gap-3');
                    if (row) {
                        var newBadge = document.createElement('span');
                        newBadge.className = 'bg-[#9400D3] text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0';
                        newBadge.textContent = '1';
                        row.appendChild(newBadge);
                    }
                }
            }
        });
    console.info('[MedFind] Real-time listening on pharmacy.{{ $pharmacy->id }}');
}

// File preview popup for pharmacy
var pendingFiles = [];
var pendingConsumerId = null;

function showFilePreview(files, consumerId) {
    pendingFiles = Array.from(files);
    pendingConsumerId = consumerId;
    var popup = document.getElementById('filePreviewPopup');
    var list = document.getElementById('filePreviewList');
    document.getElementById('filePreviewTitle').textContent = 'Send ' + pendingFiles.length + ' file' + (pendingFiles.length > 1 ? 's' : '');
    list.innerHTML = '';
    pendingFiles.forEach(function(file, idx) {
        var size = file.size < 1024*1024 ? (file.size/1024).toFixed(1) + ' KB' : (file.size/1024/1024).toFixed(1) + ' MB';
        var isImage = file.type && file.type.startsWith('image/');
        var row = document.createElement('div');
        row.className = 'flex items-center gap-3 py-2 border-b border-white/5';
        var thumbId = 'phFileThumb_' + idx;
        row.innerHTML = '<div id="' + thumbId + '" class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden" style="background:#9400D3;"><i class="fas fa-file text-white text-sm"></i></div><div class="flex-1 min-w-0"><p class="text-white text-xs font-medium truncate">' + file.name + '</p><p class="text-gray-400 text-xs">' + size + '</p></div><button onclick="removeFileFromPreview(' + idx + ')" class="text-gray-500 hover:text-red-400 transition"><i class="fas fa-times"></i></button>';
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
        showFilePreview(pendingFiles, pendingConsumerId);
    }
    input.value = '';
}

function removeFileFromPreview(idx) {
    pendingFiles.splice(idx, 1);
    if (pendingFiles.length === 0) { closeFilePreview(); return; }
    showFilePreview(pendingFiles, pendingConsumerId);
}

function closeFilePreview() {
    document.getElementById('filePreviewPopup').style.display = 'none';
    document.getElementById('filePreviewPopup').classList.add('hidden');
    pendingFiles = [];
    pendingConsumerId = null;
}

function sendWithFiles() {
    if (!pendingConsumerId || pendingFiles.length === 0) return;
    var caption = document.getElementById('fileCaption').value.trim() || '';
    var form = document.querySelector('#chat-' + pendingConsumerId + ' form');
    if (!form) return;

    var fd = new FormData(form);
    fd.set('reply', caption || 'Sent ' + pendingFiles.length + ' file(s)');
    pendingFiles.forEach(function(file) { fd.append('attachments[]', file); });

    // Show bubble immediately with image previews
    var msgsDiv = document.getElementById('chat-' + pendingConsumerId).querySelector('.chat-messages');
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

    fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    closeFilePreview();
    var msgInput = form.querySelector('input[name="reply"]');
    if (msgInput) msgInput.value = '';
}

// Show popup when files are selected via paperclip
document.addEventListener('change', function(e) {
    if (e.target.name === 'attachments[]' && e.target.files && e.target.files.length > 0) {
        var consumerId = activeConsumerId;
        if (consumerId) showFilePreview(e.target.files, consumerId);
        e.target.value = '';
    }
});
</script>

<style>
.active-conv > div:first-child { background: rgba(148, 0, 211, 0.15) !important; }
[x-cloak] { display: none !important; }
</style>

{{-- File Preview Popup --}}
<div id="filePreviewPopup" class="fixed inset-0 z-[99999] hidden items-end justify-center pb-20" style="background:rgba(0,0,0,0.5);" onclick="if(event.target===this)closeFilePreview()">
    <div class="bg-[#191970] rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden border border-[#9400D3]/30">
        <div class="flex items-center justify-between px-5 py-3 border-b border-white/10">
            <span id="filePreviewTitle" class="text-white font-bold text-sm">Send files</span>
            <div class="flex items-center gap-2">
                <label for="addMoreFiles" class="cursor-pointer text-gray-400 hover:text-[#D9F855] transition"><i class="fas fa-plus"></i></label>
                <input type="file" id="addMoreFiles" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv" multiple class="hidden" onchange="addMoreFilesToPreview(this)">
                <button onclick="closeFilePreview()" class="text-gray-400 hover:text-white transition"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div id="filePreviewList" class="max-h-64 overflow-y-auto px-5 py-3 space-y-2"></div>
        <div class="px-5 py-3 border-t border-white/10 flex items-center gap-3">
            <input type="text" id="fileCaption" placeholder="Add a caption..."
                   class="flex-1 px-4 py-2 rounded-full text-sm text-white outline-none placeholder-gray-400"
                   style="background:#2a2a5a;border:1px solid rgba(148,0,211,0.3);"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();sendWithFiles();}">
            <button onclick="sendWithFiles()" class="w-10 h-10 rounded-full flex items-center justify-center transition hover:opacity-80" style="background:#9400D3;">
                <i class="fas fa-paper-plane text-white text-sm"></i>
            </button>
        </div>
    </div>
</div>
@endsection