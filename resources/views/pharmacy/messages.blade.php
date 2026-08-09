{{-- resources/views/pharmacy/messages.blade.php --}}

@extends('layouts.app')

@section('title', 'Pharmacy Messages')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <h1 class="text-2xl font-bold text-gray-800">💬 Customer Messages</h1>
            @php $unreadCount = \App\Models\Message::where('pharmacy_id', $pharmacy->id)->where('is_read', false)->count(); @endphp
            <span id="pharmacyUnreadCountBadge" class="ml-4 text-sm bg-red-600 text-white px-2 py-1 rounded-full">{{ $unreadCount }}</span>
        </div>
        <a href="{{ route('pharmacy.dashboard') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

@if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Status filter tabs --}}
    @php $currentStatus = $status ?? 'all'; @endphp
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('pharmacy.messages', ['status' => 'all']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition duration-200 {{ $currentStatus === 'all' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
            All
        </a>
        <a href="{{ route('pharmacy.messages', ['status' => 'unread']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition duration-200 {{ $currentStatus === 'unread' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
            Unread
        </a>
        <a href="{{ route('pharmacy.messages', ['status' => 'read']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition duration-200 {{ $currentStatus === 'read' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
            Read
        </a>
        @if($currentStatus === 'unread' || $currentStatus === 'read')
            <span class="ml-auto text-sm text-gray-500 self-center">
                Showing {{ $currentStatus }} messages ({{ $messages->count() }})
            </span>
        @endif
    </div>

    @if(isset($messages) && $messages->count() > 0)
        <div class="space-y-4">
            @foreach($messages as $message)
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <h3 class="font-semibold text-gray-800">
                                    <i class="fas fa-user mr-2 text-gray-400"></i>
                                    From: {{ $message->consumer->name ?? 'Customer' }}
                                </h3>
                                @if(!$message->is_read)
                                                                    <span class="ml-3 text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full message-new">New</span>
                                @endif
                                <span class="ml-3 text-xs text-gray-400">{{ $message->created_at->format('M d, Y g:i A') }}</span>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg mb-3">
                                <p class="text-gray-700">{{ $message->message }}</p>
                            </div>
                            
                            @if($message->prescription_image)
                                <div class="mt-2 flex items-center gap-4">
                                    <a href="{{ asset('storage/' . $message->prescription_image) }}" target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-2">
                                        <img src="{{ asset('storage/' . $message->prescription_image) }}" alt="prescription" class="w-24 h-auto rounded border" />
                                        <span><i class="fas fa-file-prescription mr-1"></i>View Prescription</span>
                                    </a>

                                    {{-- Verify controls --}}
                                    <div class="ml-2">
                                        @if(!$message->verification_status)
                                           <button type="button" class="js-verify-button bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm" data-id="{{ $message->id }}">Verify</button>
                                        @else
                                           <div class="text-sm">
                                               <span class="px-2 py-1 rounded-full text-white text-xs font-semibold {{ $message->verification_status === 'approved' ? 'bg-green-600' : 'bg-red-600' }}">{{ ucfirst($message->verification_status) }}</span>
                                               <div class="text-xs text-gray-500">By: {{ $message->verifier->name ?? '-' }} at {{ optional($message->verified_at)->format('M d, Y g:i A') }}</div>
                                               @if($message->verification_notes)
                                                   <div class="mt-1 text-xs text-gray-700">Notes: {{ $message->verification_notes }}</div>
                                               @endif
                                           </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            
                            @if($message->reply)
                                <div class="mt-3 bg-purple-50 p-4 rounded-lg border-l-4 border-purple-500">
                                    <p class="text-sm text-gray-500 mb-1">Your Reply:</p>
                                    <p class="text-gray-700">{{ $message->reply }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ $message->replied_at->format('M d, Y g:i A') }}</p>
                                </div>
                            @else
                                <div class="mt-4">
                                    <form action="{{ route('pharmacy.message.reply', $message->id) }}" method="POST" class="flex flex-col gap-3">
                                        @csrf
                                        <textarea name="reply" rows="3" 
                                                  placeholder="Type your reply here..." 
                                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" 
                                                  required></textarea>
                                        <div class="flex gap-2">
                                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg transition duration-200">
                                                <i class="fas fa-reply mr-2"></i>Send Reply
                                            </button>
                                            <button type="button" data-id="{{ $message->id }}" class="js-mark-read bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition duration-200">
                                                <i class="fas fa-check mr-2"></i>Mark as Read
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-gray-50 rounded-lg p-12 text-center">
            <i class="fas fa-inbox text-gray-400 text-6xl mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">No Messages Yet</h3>
            <p class="text-gray-500">Customer messages will appear here when consumers contact you.</p>
        </div>
    @endif
</div>

<!-- Verification Modal -->
<div id="verifyModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none;">
    <div id="verifyModalBackdrop" class="absolute inset-0 bg-black opacity-50"></div>
    <div class="relative bg-white rounded-lg shadow-lg w-11/12 max-w-md p-6 z-10">
        <h2 class="text-lg font-semibold mb-4">Verify Prescription</h2>
        <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700">Decision</label>
            <div class="mt-2 flex gap-4">
                <label class="inline-flex items-center">
                    <input type="radio" name="verify_status" value="approved" checked class="form-radio text-green-600">
                    <span class="ml-2 text-sm">Approve</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" name="verify_status" value="rejected" class="form-radio text-red-600">
                    <span class="ml-2 text-sm">Reject</span>
                </label>
            </div>
        </div>
        <div class="mb-4">
            <label for="verifyNotes" class="block text-sm font-medium text-gray-700">Notes (optional)</label>
            <textarea id="verifyNotes" rows="4" class="w-full mt-2 px-3 py-2 border border-gray-300 rounded-md"></textarea>
        </div>
        <div class="flex justify-end gap-2">
            <button id="cancelVerifyBtn" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Cancel</button>
            <button id="confirmVerifyBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Confirm</button>
        </div>
    </div>
</div>

@endsection
