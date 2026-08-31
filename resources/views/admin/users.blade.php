{{-- resources/views/admin/users.blade.php --}}

@extends('layouts.app')

@section('title', 'Manage Users')

@section('content')
<div class="container mx-auto px-4 py-8">
<div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Manage Users</h1>
        <a href="{{ route('admin.dashboard') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    <!-- Search & Filter -->
    <form method="GET" action="{{ route('admin.users') }}" class="bg-white rounded-lg shadow-lg p-4 mb-6 flex flex-col sm:flex-row flex-wrap gap-3 sm:items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-600 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base sm:text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-1">Role</label>
            <select name="role" class="w-full sm:w-auto border border-gray-300 rounded-lg px-3 py-2.5 text-base sm:text-sm">
                <option value="all">All Roles</option>
                @foreach(['consumer', 'pharmacy', 'pharmacy_operator', 'admin'] as $role)
                    <option value="{{ $role }}" {{ request('role') === $role ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($role)) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
            <i class="fas fa-search mr-1"></i>Search
        </button>
        <a href="{{ route('admin.users') }}" class="text-gray-500 hover:text-gray-700 text-sm px-2 py-2 text-center">Reset</a>
    </form>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-4 sm:p-6">
            <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                <table class="w-full min-w-[640px]">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Name</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Email</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Role</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Joined</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 text-sm">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-sm">{{ $user->email }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @php
                                       $roleLabel = $user->role === 'pharmacy_operator' ? 'pharmacy' : $user->role;
                                    @endphp
                                    <span class="px-2 py-1 rounded text-xs 
                                       {{ $user->role === 'admin' ? 'bg-red-100 text-red-700' : '' }}
                                       {{ in_array($user->role, ['pharmacy', 'pharmacy_operator']) ? 'bg-blue-100 text-blue-700' : '' }}
                                       {{ $user->role === 'consumer' ? 'bg-green-100 text-green-700' : '' }}">
                                       {{ str_replace('_', ' ', ucfirst($roleLabel)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $user->created_at->format('M d, Y') }}</td>
<td class="px-4 py-3">
                                    <div class="flex gap-4 items-center">
                                        <a href="{{ route('admin.user.edit', $user->id) }}" class="text-blue-600 hover:text-blue-800 text-base py-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.user.delete', $user->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Are you sure?')" 
                                                    class="text-red-600 hover:text-red-800 text-base py-1">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
@endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
