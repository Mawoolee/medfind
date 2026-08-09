@extends('layouts.app')

@section('title', 'Returns & Recalls')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">↩️ Returns & Recalls</h1>
        <a href="{{ route('pharmacy.returns.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
            <i class="fas fa-plus mr-2"></i>New Return / Recall
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-gray-600">
                    <th class="px-4 py-3">Medicine</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Quantity</th>
                    <th class="px-4 py-3">Reason</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $r)
                    <tr class="border-t border-gray-200">
                        <td class="px-4 py-3 font-medium">{{ $r->inventoryItem->medicine->medicine_name }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs {{ $r->type === 'recall' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ ucfirst($r->type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $r->quantity }}</td>
                        <td class="px-4 py-3">{{ $r->reason ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $colors = ['pending' => 'bg-yellow-100 text-yellow-700', 'approved' => 'bg-blue-100 text-blue-700', 'completed' => 'bg-green-100 text-green-700', 'rejected' => 'bg-red-100 text-red-700'];
                            @endphp
                            <form method="POST" action="{{ route('pharmacy.returns.status', $r->id) }}" class="inline">
                                @csrf
                                <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-xs {{ $colors[$r->status] ?? '' }}">
                                    @foreach(['pending','approved','completed','rejected'] as $s)
                                        <option value="{{ $s }}" {{ $r->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($r->created_at)->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No returns or recalls recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
