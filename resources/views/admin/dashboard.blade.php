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

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
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
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Inventory Overview</h2>
            <p class="text-gray-600 mb-4">View real-time stock across all pharmacies.</p>
            <a href="{{ route('admin.inventory') }}" class="inline-block bg-[#9400D3] hover:bg-[#7a00b0] text-white px-6 py-2 rounded-lg transition duration-200">
                <i class="fas fa-boxes-stacked mr-2"></i>View Inventory
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

    {{-- ISO Survey Card --}}
    <div class="bg-gradient-to-r from-[#191970] to-[#2a2a8a] rounded-lg shadow-lg p-6 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-[#D9F855] mb-1">ISO/IEC 25010 Software Quality</p>
            <h2 class="text-xl font-bold text-white mb-1">System Evaluation Survey</h2>
            <p class="text-blue-200 text-sm">Collect feedback on Functional Suitability, Usability, and Security from your respondents.</p>
        </div>
        <div class="flex gap-3 flex-shrink-0">
            <a href="{{ route('survey.show') }}" target="_blank"
               class="inline-block bg-[#D9F855] text-[#191970] font-bold px-5 py-2 rounded-lg text-sm hover:bg-yellow-300 transition">
                <i class="fas fa-external-link-alt mr-2"></i>Open Survey
            </a>
            <a href="{{ route('admin.survey.results') }}"
               class="inline-block bg-white/20 text-white font-semibold px-5 py-2 rounded-lg text-sm hover:bg-white/30 transition">
                <i class="fas fa-chart-bar mr-2"></i>View Results
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
