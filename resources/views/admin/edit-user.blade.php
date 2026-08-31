{{-- resources/views/admin/edit-user.blade.php --}}

@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Edit User</h1>
        <a href="{{ route('admin.users') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-5 sm:p-6 max-w-2xl">
        <form action="{{ route('admin.user.update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="name" class="block text-gray-700 text-sm font-medium mb-2">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-medium mb-2">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label for="role" class="block text-gray-700 text-sm font-medium mb-2">Role</label>
                <select id="role" name="role" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @foreach (['consumer', 'pharmacy', 'pharmacy_operator', 'admin'] as $role)
                        <option value="{{ $role }}" {{ old('role', $user->role) === $role ? 'selected' : '' }}>
                            {{ str_replace('_', ' ', ucfirst($role)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit" class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg transition duration-200 w-full sm:w-auto">
                    <i class="fas fa-save mr-2"></i>Update User
                </button>
                <a href="{{ route('admin.users') }}" class="inline-flex items-center justify-center bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2.5 rounded-lg transition duration-200 w-full sm:w-auto">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
