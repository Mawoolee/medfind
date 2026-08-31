{{-- resources/views/admin/dashboard.blade.php --}}

@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
 <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-6">Admin Dashboard</h1>

 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
 <div class="bg-white rounded-lg shadow-lg p-5 sm:p-6">
 <div class="flex items-center justify-between">
 <div>
 <p class="text-gray-500 text-sm">Total Users</p>
 <p class="text-2xl font-bold text-blue-600">{{ \App\Models\User::count() }}</p>
 </div>
 <i class="fas fa-users text-4xl text-blue-200"></i>
 </div>
 </div>
 <div class="bg-white rounded-lg shadow-lg p-5 sm:p-6">
 <div class="flex items-center justify-between">
 <div>
 <p class="text-gray-500 text-sm">Pharmacies</p>
 <p class="text-2xl font-bold text-green-600">{{ \App\Models\Pharmacy::count() }}</p>
 </div>
 <i class="fas fa-store text-4xl text-green-200"></i>
 </div>
 </div>
 <div class="bg-white rounded-lg shadow-lg p-5 sm:p-6">
 <div class="flex items-center justify-between">
 <div>
 <p class="text-gray-500 text-sm">Medicines</p>
 <p class="text-2xl font-bold text-purple-600">{{ \App\Models\Medicine::count() }}</p>
 </div>
 <i class="fas fa-capsules text-4xl text-purple-200"></i>
 </div>
 </div>
 </div>

 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
 <div class="bg-white rounded-lg shadow-lg p-5 sm:p-6 flex flex-col">
 <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-2">Manage Users</h2>
 <p class="text-gray-600 mb-4 flex-1">View and manage all system users.</p>
 <a href="{{ route('admin.users') }}" class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition duration-200 w-full sm:w-fit">
 <i class="fas fa-user-cog mr-2"></i>Manage Users
 </a>
 </div>
 <div class="bg-white rounded-lg shadow-lg p-5 sm:p-6 flex flex-col">
 <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-2">Manage Pharmacies</h2>
 <p class="text-gray-600 mb-4 flex-1">Add, edit, or remove pharmacies.</p>
 <a href="{{ route('admin.pharmacies') }}" class="inline-flex items-center justify-center bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition duration-200 w-full sm:w-fit">
 <i class="fas fa-store-alt mr-2"></i>Manage Pharmacies
 </a>
 </div>
 <div class="bg-white rounded-lg shadow-lg p-5 sm:p-6 flex flex-col">
 <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-2">Review Requirements</h2>
 <p class="text-gray-600 mb-4 flex-1">Review pharmacy documents and approve or reject registrations.</p>
 <a href="{{ route('admin.requirements') }}" class="inline-flex items-center justify-center text-white px-6 py-3 rounded-lg transition duration-200 w-full sm:w-fit" style="background:#9400D3;">
 <i class="fas fa-file-circle-check mr-2"></i>Review Requirements
 </a>
 </div>
 </div>

 <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
 <div class="bg-white rounded-lg shadow-lg overflow-hidden">
 <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
 <h2 class="text-lg font-semibold text-gray-800">Recent Users</h2>
 <a href="{{ route('admin.users') }}" class="text-sm text-blue-600 hover:text-blue-800">View all</a>
 </div>
 <div class="divide-y divide-gray-100">
 @forelse($recentUsers as $user)
 <div class="px-6 py-3 flex items-center justify-between">
 <div>
 <p class="font-medium text-gray-800">{{ $user->name }}</p>
 <p class="text-sm text-gray-500">{{ $user->email }}</p>
 </div>
 <span class="px-2 py-1 rounded text-xs
 {{ $user->role === 'admin' ? 'bg-red-100 text-red-700' : '' }}
 {{ in_array($user->role, ['pharmacy', 'pharmacy_operator']) ? 'bg-blue-100 text-blue-700' : '' }}
 {{ $user->role === 'consumer' ? 'bg-green-100 text-green-700' : '' }}">
 {{ str_replace('_', ' ', ucfirst($user->role)) }}
 </span>
 </div>
 @empty
 <p class="px-6 py-4 text-gray-500 text-sm">No users found.</p>
 @endforelse
 </div>
 </div>

 <div class="bg-white rounded-lg shadow-lg overflow-hidden">
 <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
 <h2 class="text-lg font-semibold text-gray-800">Recent Pharmacies</h2>
 <a href="{{ route('admin.pharmacies') }}" class="text-sm text-blue-600 hover:text-blue-800">View all</a>
 </div>
 <div class="divide-y divide-gray-100">
 @forelse($recentPharmacies as $pharmacy)
 <div class="px-6 py-3 flex items-center justify-between">
 <div>
 <p class="font-medium text-gray-800">{{ $pharmacy->pharmacy_name }}</p>
 <p class="text-sm text-gray-500">{{ $pharmacy->pharmacyAddress }}</p>
 </div>
 <span class="px-2 py-1 rounded text-xs
 {{ $pharmacy->status === 'approved' ? 'bg-green-100 text-green-700' : '' }}
 {{ $pharmacy->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
 {{ $pharmacy->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}">
 {{ ucfirst($pharmacy->status) }}
 </span>
 </div>
 @empty
 <p class="px-6 py-4 text-gray-500 text-sm">No pharmacies found.</p>
 @endforelse
 </div>
 </div>
 </div>
</div>
@endsection
