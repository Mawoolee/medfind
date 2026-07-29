{{-- resources/views/consumer/messages.blade.php --}}

@extends('layouts.app')

@section('title', 'My Messages')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">My Conversations</h1>
        <a href="{{ route('consumer.dashboard') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    @if(isset($messages) && $messages->count() > 0)
        <div class="space-y-4">
            @foreach($messages as $message)
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <h3 class="font-semibold text-gray-800">{{ $message->pharmacy->pharmacy_name }}</h3>
                                @if($message->is_read)
                                    <span class="ml-3 text-xs text-green-600 bg-green-100 px-2 py-1 rounded-full">Read</span>
                                @else
                                    <span class="ml-3 text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">New</span>
                                @endif
                            </div>
                            <p class="text-gray-700 mb-2">{{ $message->message }}</p>
                            
                            @if($message->prescription_image)
                                <div class="mt-2">
                                    <a href="{{ asset('storage/' . $message->prescription_image) }}" target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-file-prescription mr-1"></i>View Prescription
                                    </a>
                                </div>
                            @endif
                            
                            @if($message->reply)
                                <div class="mt-3 bg-gray-50 p-4 rounded-lg border-l-4 border-purple-500">
                                    <p class="text-sm text-gray-500 mb-1">Pharmacy Reply:</p>
                                    <p class="text-gray-700">{{ $message->reply }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ $message->replied_at->format('M d, Y g:i A') }}</p>
                                </div>
                            @endif
                            
                            <p class="text-xs text-gray-400 mt-2">{{ $message->created_at->format('M d, Y g:i A') }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-gray-50 rounded-lg p-12 text-center">
            <i class="fas fa-envelope-open text-gray-400 text-6xl mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">No Messages Yet</h3>
            <p class="text-gray-500">Start searching for medicines and chatting with pharmacies.</p>
        </div>
    @endif
</div>
@endsection