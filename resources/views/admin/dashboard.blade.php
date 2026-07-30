{{-- resources/views/admin/dashboard.blade.php --}}

@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Admin Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Users</p>
                    <p class="text-2xl font-bold text-blue-600">{{ \App\Models\User::count() }}</p>
                </div>
                <i class="fas fa-users text-4xl text-blue-200"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Pharmacies</p>
                    <p class="text-2xl font-bold text-green-600">{{ \App\Models\Pharmacy::count() }}</p>
                </div>
                <i class="fas fa-store text-4xl text-green-200"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Medicines</p>
                    <p class="text-2xl font-bold text-purple-600">{{ \App\Models\Medicine::count() }}</p>
                </div>
                <i class="fas fa-capsules text-4xl text-purple-200"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Messages</p>
                    <p class="text-2xl font-bold text-orange-600">{{ \App\Models\Message::count() }}</p>
                </div>
                <i class="fas fa-envelope text-4xl text-orange-200"></i>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Manage Users</h2>
            <p class="text-gray-600 mb-4">View and manage all system users.</p>
            <a href="{{ route('admin.users') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition duration-200">
                <i class="fas fa-user-cog mr-2"></i>Manage Users
            </a>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Manage Pharmacies</h2>
            <p class="text-gray-600 mb-4">Add, edit, or remove pharmacies.</p>
            <a href="{{ route('admin.pharmacies') }}" class="inline-block bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition duration-200">
                <i class="fas fa-store-alt mr-2"></i>Manage Pharmacies
            </a>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">System Logs</h2>
            <p class="text-gray-600 mb-4">View system activity logs.</p>
            <a href="{{ route('admin.logs') }}" class="inline-block bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg transition duration-200">
                <i class="fas fa-clipboard-list mr-2"></i>View Logs
            </a>
        </div>
    </div>
</div>
@endsection