@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Suppliers</h1>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('pharmacy.suppliers.create') }}" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 min-h-11 rounded">
                <i class="fas fa-plus mr-2"></i>Add Supplier
            </a>
            <x-back-button :href="route('pharmacy.dashboard')" label="Back to Pharmacy Dashboard" />
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[800px]">
                <thead>
                    <tr class="bg-gray-50 text-left text-gray-600">
                        <th class="px-4 py-3 whitespace-nowrap">Name</th>
                        <th class="px-4 py-3 whitespace-nowrap">Contact</th>
                        <th class="px-4 py-3 whitespace-nowrap">Phone</th>
                        <th class="px-4 py-3 whitespace-nowrap">Email</th>
                        <th class="px-4 py-3 whitespace-nowrap">Address</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $s)
                    <tr class="border-t border-gray-200">
                        <td class="px-4 py-3 font-medium whitespace-nowrap">{{ $s->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->contact_person }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->phone }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->email }}</td>
                        <td class="px-4 py-3">{{ $s->address }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('pharmacy.suppliers.edit', $s->id) }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1.5 rounded text-xs">Edit</a>
                                <form action="{{ route('pharmacy.suppliers.destroy', $s->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete supplier?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No suppliers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($suppliers->hasPages())
        <div class="mt-4">{{ $suppliers->links() }}</div>
    @endif
</div>
@endsection
