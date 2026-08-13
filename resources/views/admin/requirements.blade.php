{{-- resources/views/admin/requirements.blade.php --}}

@extends('layouts.app')

@section('title', 'Requirements Review')

@section('content')
<div class="min-h-screen" style="background:#f0f0ff;">
    <div class="max-w-7xl mx-auto px-4 py-8">

        {{-- Page Header --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.dashboard') }}"
                   class="text-[#9400D3] hover:text-[#191970] transition"
                   title="Back to Dashboard">
                    <i class="fas fa-arrow-left text-lg"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-[#191970]">
                        <i class="fas fa-file-check mr-2 text-[#9400D3]"></i>Requirements Review
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">Review pharmacy documents and approve or reject registrations</p>
                </div>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3">
                <i class="fas fa-check-circle text-green-500"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Filter Bar --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <form method="GET" action="{{ route('admin.requirements') }}" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[160px]">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                    <select name="status"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30 focus:border-[#9400D3]">
                        <option value="pending"  {{ request('status', 'pending') === 'pending'  ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="all"      {{ request('status') === 'all'      ? 'selected' : '' }}>All</option>
                    </select>
                </div>
                <div>
                    <button type="submit"
                            class="bg-[#9400D3] hover:bg-[#7a00b0] text-white text-sm font-semibold px-5 py-2 rounded-lg transition">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                </div>
                <div>
                    <a href="{{ route('admin.requirements') }}"
                       class="text-sm text-gray-500 hover:text-[#9400D3] transition px-3 py-2 inline-block">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Results count --}}
        <p class="text-sm text-gray-500 mb-3">
            Showing <strong>{{ $pharmacies->total() }}</strong> {{ Str::plural('pharmacy', $pharmacies->total()) }}
            @if(request('status') && request('status') !== 'all')
                with status <strong>{{ ucfirst(request('status', 'pending')) }}</strong>
            @endif
        </p>

        {{-- Pharmacy Cards --}}
        @forelse($pharmacies as $pharmacy)
            @php
                $docs = is_array($pharmacy->requirements) ? $pharmacy->requirements : (json_decode($pharmacy->requirements, true) ?? []);
                $docCount = count($docs);

                $docMap = [
                    'bir'         => 'BIR Certificate of Registration',
                    'business'    => "Mayor's / Business Permit",
                    'philhealth'  => 'PhilHealth Accreditation (optional)',
                    'fda'         => 'FDA Certificate (optional)',
                    'pharmacist'  => 'Pharmacist License',
                ];

                $statusColors = [
                    'pending'  => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                    'approved' => 'bg-green-100 text-green-700 border-green-200',
                    'rejected' => 'bg-red-100 text-red-700 border-red-200',
                ];
                $statusColor = $statusColors[$pharmacy->status] ?? 'bg-gray-100 text-gray-600 border-gray-200';
            @endphp

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 overflow-hidden"
                 x-data="{ open: false }">

                {{-- Card Header (always visible) --}}
                <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                             style="background:#f0f0ff;">
                            <i class="fas fa-hospital text-[#9400D3]"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-[#191970] text-base leading-tight">
                                {{ $pharmacy->pharmacy_name }}
                            </h3>
                            <p class="text-sm text-gray-500 mt-0.5">
                                <i class="fas fa-user mr-1"></i>
                                {{ $pharmacy->user?->name ?? 'Unknown owner' }}
                                @if($pharmacy->user?->email)
                                    &middot; <span class="text-gray-400">{{ $pharmacy->user->email }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        {{-- Submitted date --}}
                        <span class="text-xs text-gray-400">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            Submitted {{ $pharmacy->created_at->format('M d, Y') }}
                        </span>

                        {{-- Document count --}}
                        <span class="text-xs font-semibold px-2 py-1 rounded-full bg-[#9400D3]/10 text-[#9400D3]">
                            <i class="fas fa-paperclip mr-1"></i>{{ $docCount }} {{ Str::plural('doc', $docCount) }}
                        </span>

                        {{-- Status badge --}}
                        <span class="text-xs font-bold px-3 py-1 rounded-full border {{ $statusColor }}">
                            @if($pharmacy->status === 'pending')
                                <i class="fas fa-clock mr-1"></i>
                            @elseif($pharmacy->status === 'approved')
                                <i class="fas fa-check-circle mr-1"></i>
                            @else
                                <i class="fas fa-times-circle mr-1"></i>
                            @endif
                            {{ ucfirst($pharmacy->status) }}
                        </span>

                        {{-- Review toggle button --}}
                        <button type="button"
                                @click="open = !open"
                                class="text-sm font-semibold px-4 py-1.5 rounded-lg border transition"
                                :class="open
                                    ? 'bg-[#191970] text-white border-[#191970]'
                                    : 'bg-white text-[#191970] border-[#191970] hover:bg-[#191970] hover:text-white'">
                            <i class="fas fa-eye mr-1"></i>
                            <span x-text="open ? 'Close' : 'Review'"></span>
                        </button>
                    </div>
                </div>

                {{-- Expandable Review Section --}}
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="border-t border-gray-100"
                     style="display:none;">

                    <div class="px-6 py-5" style="background:#faf9ff;">

                        <h4 class="text-sm font-bold text-[#191970] uppercase tracking-wide mb-4">
                            <i class="fas fa-folder-open mr-2 text-[#9400D3]"></i>Submitted Documents
                        </h4>

                        {{-- Document checklist with view links --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                            @foreach($docMap as $key => $label)
                                @php $isUploaded = array_key_exists($key, $docs); @endphp
                                <div class="flex items-center gap-3 px-4 py-3 rounded-lg border
                                    {{ $isUploaded ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200' }}">
                                    @if($isUploaded)
                                        <span class="w-6 h-6 flex items-center justify-center rounded-full bg-green-100 text-green-600 flex-shrink-0">
                                            <i class="fas fa-check text-xs"></i>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-semibold text-green-700 truncate">{{ $label }}</p>
                                            @php $fileUrl = route('admin.requirement.file', ['pharmacy' => $pharmacy->id, 'key' => $key]); @endphp
                                            <button type="button"
                                               onclick="openFileModal('{{ $fileUrl }}')"
                                               class="text-xs font-semibold text-[#9400D3] hover:text-[#191970] transition flex items-center gap-1 mt-0.5">
                                                <i class="fas fa-eye text-[10px]"></i> View File
                                            </button>
                                        </div>
                                    @else
                                        <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 text-gray-400 flex-shrink-0">
                                            <i class="fas fa-times text-xs"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-gray-500 truncate">{{ $label }}</p>
                                            <p class="text-xs text-gray-400">Not submitted</p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Action Buttons --}}
                        @if($pharmacy->status === 'pending')
                            <div class="flex flex-wrap gap-3">
                                {{-- Approve --}}
                                <form method="POST"
                                      action="{{ route('admin.requirements.approve', $pharmacy) }}"
                                      onsubmit="return confirm('Approve {{ addslashes($pharmacy->pharmacy_name) }}?')">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-bold bg-green-600 hover:bg-green-700 text-white transition shadow-sm">
                                        <i class="fas fa-check"></i> Approve ✓
                                    </button>
                                </form>

                                {{-- Reject --}}
                                <form method="POST"
                                      action="{{ route('admin.requirements.reject', $pharmacy) }}"
                                      onsubmit="return confirm('Reject {{ addslashes($pharmacy->pharmacy_name) }}?')">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-bold bg-red-600 hover:bg-red-700 text-white transition shadow-sm">
                                        <i class="fas fa-times"></i> Reject ✗
                                    </button>
                                </form>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 italic">
                                This pharmacy has already been
                                <strong>{{ $pharmacy->status }}</strong>.
                                You can change the status from
                                <a href="{{ route('admin.pharmacy.edit', $pharmacy) }}"
                                   class="text-[#9400D3] hover:underline">Edit Pharmacy</a>.
                            </p>
                        @endif
                    </div>
                </div>

            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-8 py-16 text-center">
                <i class="fas fa-folder-open text-5xl text-gray-200 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-500">No pharmacies found</h3>
                <p class="text-sm text-gray-400 mt-1">
                    There are no pharmacies with submitted requirements matching the current filter.
                </p>
                @if(request('status') && request('status') !== 'pending')
                    <a href="{{ route('admin.requirements') }}"
                       class="mt-4 inline-block text-sm text-[#9400D3] hover:underline">
                        View pending pharmacies
                    </a>
                @endif
            </div>
        @endforelse

        {{-- Pagination --}}
        @if($pharmacies->hasPages())
            <div class="mt-6">
                {{ $pharmacies->links() }}
            </div>
        @endif

    </div>
</div>

{{-- File Preview Modal --}}
<div id="filePreviewModal"
     class="fixed inset-0 z-[99999] hidden items-center justify-center"
     style="background:rgba(0,0,0,0.7);"
     onclick="if(event.target===this)closeFileModal()">
    <div class="bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden"
         style="width:90vw;max-width:960px;height:90vh;">
        {{-- Modal header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200" style="background:#191970;">
            <span class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fas fa-file-alt text-[#D9F855]"></i>
                <span id="fileModalTitle">Document Preview</span>
            </span>
            <button onclick="closeFileModal()"
                class="w-7 h-7 rounded-full flex items-center justify-center text-white hover:bg-white/20 transition text-sm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        {{-- iframe viewer --}}
        <div class="flex-1 overflow-hidden relative">
            <iframe id="filePreviewFrame"
                    src=""
                    class="w-full h-full border-0"
                    style="min-height:0;">
            </iframe>
            <div id="fileLoadingSpinner"
                 class="absolute inset-0 flex items-center justify-center"
                 style="background:#f0f0ff;">
                <div class="flex flex-col items-center gap-3">
                    <div class="w-10 h-10 border-4 border-[#9400D3] border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-sm font-semibold text-[#191970]">Loading document...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openFileModal(url) {
    var modal = document.getElementById('filePreviewModal');
    var frame = document.getElementById('filePreviewFrame');
    var spinner = document.getElementById('fileLoadingSpinner');

    // Reset
    frame.src = '';
    spinner.style.display = 'flex';
    modal.style.display = 'flex';
    modal.classList.remove('hidden');

    // Load file into iframe
    frame.onload = function() {
        spinner.style.display = 'none';
    };
    frame.src = url;
}

function closeFileModal() {
    var modal = document.getElementById('filePreviewModal');
    var frame = document.getElementById('filePreviewFrame');
    frame.src = '';
    modal.style.display = 'none';
    modal.classList.add('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFileModal();
});
</script>@endsection
