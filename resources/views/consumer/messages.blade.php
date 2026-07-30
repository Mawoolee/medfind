@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">My Conversations</h1>
        <a href="{{ route('consumer.dashboard') }}" class="text-blue-600 hover:text-blue-800 font-medium">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
    </div>

    @if(isset($messages) && $messages->count() > 0)
        <div class="space-y-4">
            @foreach($messages as $message)
                <div class="bg-white rounded-2xl card-shadow p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center mr-3">
                                    <i class="fas fa-store text-purple-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">{{ $message->pharmacy->pharmacy_name }}</h3>
                                    <p class="text-xs text-gray-400">{{ $message->created_at->format('M d, Y g:i A') }}</p>
                                </div>
                                @if(!$message->is_read)
                                    <span class="ml-3 text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-medium">New</span>
                                @endif
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-4 mb-3">
                                <p class="text-gray-700">{{ $message->message }}</p>
                            </div>
                            
                            @if($message->prescription_image)
                                <div class="mb-3">
                                    <a href="{{ asset('storage/' . $message->prescription_image) }}" target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        <i class="fas fa-file-prescription mr-1"></i> View Prescription
                                    </a>
                                </div>
                            @endif
                            
                            @if($message->reply)
                                <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded-xl">
                                    <p class="text-sm text-gray-500 mb-1">Pharmacy Reply:</p>
                                    <p class="text-gray-700">{{ $message->reply }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ $message->replied_at->format('M d, Y g:i A') }}</p>
                                </div>
                            @else
                                <div class="text-sm text-gray-400 italic">Waiting for pharmacy response...</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-gray-50 rounded-2xl p-12 text-center">
            <i class="fas fa-envelope-open text-gray-400 text-5xl mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">No Messages Yet</h3>
            <p class="text-gray-500">Start searching for medicines and chatting with pharmacies.</p>
        </div>
    @endif
</div>
@endsection