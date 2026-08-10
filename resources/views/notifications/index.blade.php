@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🔔 Notifications</h1>
            @if($unreadCount > 0)
                <p class="text-sm text-[#9400D3] mt-1 font-medium">{{ $unreadCount }} unread notification{{ $unreadCount === 1 ? '' : 's' }}</p>
            @else
                <p class="text-sm text-gray-500 mt-1">You're all caught up.</p>
            @endif
        </div>
        @if($notifications->isNotEmpty())
            <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                @csrf
                <button type="submit" class="text-sm text-[#9400D3] hover:text-[#7a00b0] font-medium transition">
                    <i class="fas fa-check-double mr-1"></i>Mark all as read
                </button>
            </form>
        @endif
    </div>

    <div class="space-y-3">
        @forelse($notifications as $notification)
            @php
                $data = $notification->data;
                $isRead = !is_null($notification->read_at);
            @endphp
            <div class="bg-white rounded-lg shadow-sm border {{ $isRead ? 'border-gray-100' : 'border-[#9400D3]/30' }} p-4 flex items-start gap-4 transition">
                {{-- Icon --}}
                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center
                    {{ $isRead ? 'bg-gray-100' : 'bg-[#9400D3]/10' }}">
                    @php
                        $type = $data['type'] ?? 'info';
                    @endphp
                    @if($type === 'approved')
                        <i class="fas fa-check-circle text-green-600 text-lg"></i>
                    @elseif($type === 'rejected')
                        <i class="fas fa-times-circle text-red-600 text-lg"></i>
                    @elseif($type === 'pending')
                        <i class="fas fa-clock text-yellow-500 text-lg"></i>
                    @else
                        <i class="fas fa-bell text-[#9400D3] text-lg"></i>
                    @endif
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 {{ $isRead ? '' : 'text-[#191970]' }}">
                        {{ $data['title'] ?? 'Notification' }}
                    </p>
                    <p class="text-sm text-gray-600 mt-0.5">{{ $data['message'] ?? '' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                </div>

                {{-- Read indicator + action --}}
                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                    @if(!$isRead)
                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-[#9400D3]"></span>
                    @endif
                    <form method="POST" action="{{ route('notifications.mark-read', $notification->id) }}">
                        @csrf
                        <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 transition {{ $isRead ? 'invisible' : '' }}">
                            Mark read
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-12 text-center">
                <i class="fas fa-bell-slash text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-gray-500 font-medium">No notifications yet</p>
                <p class="text-sm text-gray-400 mt-1">You'll see updates about your pharmacy status and system alerts here.</p>
            </div>
        @endforelse
    </div>

    @if($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
