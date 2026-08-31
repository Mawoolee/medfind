{{-- resources/views/admin/activity.blade.php --}}

@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Activity Log</h1>
        <a href="{{ route('admin.dashboard') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('admin.activity') }}" class="bg-white rounded-lg shadow-lg p-4 mb-6 flex flex-col sm:flex-row flex-wrap gap-3 sm:items-end">
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-1">Action</label>
            <select name="action" class="w-full sm:w-auto border border-gray-300 rounded-lg px-3 py-2.5 text-base sm:text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400">
                <option value="all">All Actions</option>
                @foreach(['created', 'updated', 'deleted', 'approved', 'rejected'] as $act)
                    <option value="{{ $act }}" {{ request('action') === $act ? 'selected' : '' }}>{{ ucfirst($act) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-1">Entity</label>
            <select name="entity" class="w-full sm:w-auto border border-gray-300 rounded-lg px-3 py-2.5 text-base sm:text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400">
                <option value="all">All Entities</option>
                @foreach(['User', 'Pharmacy', 'Medicine'] as $entity)
                    <option value="{{ $entity }}" {{ request('entity') === $entity ? 'selected' : '' }}>{{ $entity }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
            <i class="fas fa-filter mr-1"></i>Filter
        </button>
        <a href="{{ route('admin.activity') }}" class="text-gray-500 hover:text-gray-700 text-sm px-2 py-2 text-center">Reset</a>
    </form>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-4 sm:p-6">
            <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                <table class="w-full min-w-[720px]">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-sm text-gray-600">User</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Action</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Entity</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">Details</th>
                            <th class="px-4 py-3 text-left text-sm text-gray-600">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $activity)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 text-sm">{{ $activity->user->name ?? 'System' }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs
                                        {{ $activity->action === 'created' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $activity->action === 'updated' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $activity->action === 'deleted' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $activity->action === 'approved' ? 'bg-teal-100 text-teal-700' : '' }}
                                        {{ $activity->action === 'rejected' ? 'bg-orange-100 text-orange-700' : '' }}">
                                        {{ ucfirst($activity->action) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $activity->entity_type ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $activity->details ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{{ $activity->created_at->format('M d, Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">No activity found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $activities->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
