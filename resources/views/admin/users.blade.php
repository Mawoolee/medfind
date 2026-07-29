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

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-gray-600">Name</th>
                            <th class="px-4 py-3 text-left text-gray-600">Email</th>
                            <th class="px-4 py-3 text-left text-gray-600">Role</th>
                            <th class="px-4 py-3 text-left text-gray-600">Joined</th>
                            <th class="px-4 py-3 text-left text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3">{{ $user->name }}</td>
                                <td class="px-4 py-3">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs 
                                        {{ $user->role === 'admin' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $user->role === 'pharmacy' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $user->role === 'consumer' ? 'bg-green-100 text-green-700' : '' }}">
                                        {{ ucfirst($user->role) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $user->created_at->format('M d, Y') }}</td>
                                <td class="px-4 py-3">
                                    <form action="{{ route('admin.user.delete', $user->id) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Are you sure?')" 
                                                class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection