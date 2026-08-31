{{-- resources/views/admin/logs.blade.php --}}

@extends('layouts.app')

@section('title', 'System Logs')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">System Logs</h1>
        <a href="{{ route('admin.dashboard') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-4 sm:p-6">
            @if (empty($logs))
                <p class="text-gray-500 text-center py-8">No logs found.</p>
            @else
                <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0 max-h-[70vh] overflow-y-auto">
                    <table class="w-full min-w-[560px]">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-sm text-gray-600">#</th>
                                <th class="px-4 py-3 text-left text-sm text-gray-600">Log Entry</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $index => $log)
                                <tr class="border-t border-gray-200">
                                    <td class="px-4 py-2 text-gray-400 text-sm">{{ $index + 1 }}</td>
                                    <td class="px-4 py-2 font-mono text-xs sm:text-sm whitespace-pre-wrap break-words">{{ $log }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
