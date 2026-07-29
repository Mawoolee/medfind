{{-- resources/views/consumer/dashboard.blade.php --}}

@extends('layouts.app')

@section('title', 'Consumer Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Welcome to MedFind</h1>
    
    <!-- Search Section -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Find Your Medicine</h2>
        <form action="{{ route('consumer.search') }}" method="GET" class="flex flex-col md:flex-row gap-4">
            <input type="text" 
                   name="query" 
                   placeholder="Search for a medicine name..." 
                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
            <button type="submit" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3 rounded-lg transition duration-200">
                <i class="fas fa-search mr-2"></i>Search
            </button>
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Partner Pharmacies</p>
                    <p class="text-2xl font-bold text-blue-600">{{ \App\Models\Pharmacy::count() }}</p>
                </div>
                <i class="fas fa-store text-4xl text-blue-200"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Medicines Available</p>
                    <p class="text-2xl font-bold text-green-600">{{ \App\Models\Medicine::count() }}</p>
                </div>
                <i class="fas fa-capsules text-4xl text-green-200"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Your Messages</p>
                    <p class="text-2xl font-bold text-purple-600">{{ \App\Models\Message::where('consumer_id', auth()->id())->count() }}</p>
                </div>
                <i class="fas fa-envelope text-4xl text-purple-200"></i>
            </div>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition duration-200">
            <div class="text-blue-600 mb-4">
                <i class="fas fa-map-marked-alt text-4xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Real-Time Location</h3>
            <p class="text-gray-600">Find pharmacies near you with available medicines in real-time.</p>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition duration-200">
            <div class="text-green-600 mb-4">
                <i class="fas fa-sync-alt text-4xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Live Inventory</h3>
            <p class="text-gray-600">Check real-time stock availability before visiting the pharmacy.</p>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition duration-200">
            <div class="text-purple-600 mb-4">
                <i class="fas fa-comment-dots text-4xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Direct Messaging</h3>
            <p class="text-gray-600">Chat with pharmacies to verify prescriptions and availability.</p>
        </div>
    </div>
</div>
@endsection