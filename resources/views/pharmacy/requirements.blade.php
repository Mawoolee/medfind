@extends('layouts.app')

@section('title', 'Upload Business Requirements')

@section('content')
<div class="min-h-screen" style="background:#f0f0ff;">
<div class="container mx-auto px-4 py-10 max-w-2xl">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-8">
        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background:#191970;">
            <i class="fas fa-file-medical text-white text-lg"></i>
        </div>
        <div>
            <h1 class="text-2xl font-bold" style="color:#191970;">Upload Business Requirements</h1>
            <p class="text-sm text-gray-500">Complete your pharmacy registration by submitting the required documents</p>
        </div>
    </div>

    {{-- Status badge --}}
    <div class="flex items-center gap-2 mb-6">
        <span class="text-sm font-medium text-gray-600">Current Status:</span>
        @php
            $statusColors = [
                'pending'  => 'bg-amber-100 text-amber-800 border-amber-300',
                'approved' => 'bg-green-100 text-green-800 border-green-300',
                'rejected' => 'bg-red-100 text-red-800 border-red-300',
                'inactive' => 'bg-gray-100 text-gray-700 border-gray-300',
            ];
            $statusColor = $statusColors[$pharmacy->status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
        @endphp
        <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $statusColor }}">
            {{ ucfirst($pharmacy->status) }}
        </span>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-xl mb-5 flex items-center gap-2">
            <i class="fas fa-check-circle text-green-500"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('info'))
        <div class="bg-blue-50 border border-blue-300 text-blue-800 px-4 py-3 rounded-xl mb-5 flex items-center gap-2">
            <i class="fas fa-info-circle text-blue-500"></i>
            {{ session('info') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-xl mb-5 flex items-center gap-2">
            <i class="fas fa-exclamation-circle text-red-500"></i>
            {{ session('error') }}
        </div>
    @endif

    {{-- Already submitted notice --}}
    @if(!empty($pharmacy->requirements) && count($pharmacy->requirements) > 0)
    <div class="rounded-xl p-4 mb-6 border" style="background:rgba(148,0,211,0.05);border-color:rgba(148,0,211,0.2);">
        <div class="flex items-center gap-2 mb-1">
            <i class="fas fa-folder-open" style="color:#9400D3;"></i>
            <span class="font-semibold text-sm" style="color:#191970;">Documents Already Submitted</span>
        </div>
        <p class="text-sm text-gray-600">
            You have submitted <strong>{{ count($pharmacy->requirements) }}</strong> document{{ count($pharmacy->requirements) === 1 ? '' : 's' }}.
            You can upload additional documents below if needed.
        </p>
    </div>
    @endif

    {{-- Info card --}}
    <div class="bg-white rounded-[20px] shadow-sm p-6 mb-6 border border-gray-100">
        <h2 class="font-semibold mb-3" style="color:#191970;">
            <i class="fas fa-circle-info mr-2" style="color:#9400D3;"></i>
            Required Documents
        </h2>
        <p class="text-sm text-gray-600 mb-4">
            To complete your pharmacy registration, please upload the following documents.
            Accepted formats: <strong>JPG, PNG, PDF</strong>. Max <strong>10MB</strong> per file.
        </p>
        <ul class="space-y-2">
            @php
                $docs = [
                    ['label' => 'BIR Certificate of Registration', 'required' => true],
                    ['label' => "Mayor's / Business Permit", 'required' => true],
                    ['label' => 'PhilHealth Accreditation', 'required' => false],
                    ['label' => 'FDA Certificate', 'required' => false],
                    ['label' => 'Pharmacist License', 'required' => true],
                ];
            @endphp
            @foreach($docs as $doc)
            <li class="flex items-center gap-3">
                <span class="w-5 h-5 rounded border-2 flex items-center justify-center flex-shrink-0"
                    style="{{ $doc['required'] ? 'border-color:#9400D3;background:rgba(148,0,211,0.08);' : 'border-color:#d1d5db;background:#f9fafb;' }}">
                    @if($doc['required'])
                        <i class="fas fa-check text-[8px]" style="color:#9400D3;"></i>
                    @endif
                </span>
                <span class="text-sm text-gray-700">
                    {{ $doc['label'] }}
                    @if(!$doc['required'])
                        <span class="text-xs text-gray-400 ml-1">(optional)</span>
                    @endif
                </span>
            </li>
            @endforeach
        </ul>
    </div>

    {{-- Upload form --}}
    <div class="bg-white rounded-[20px] shadow-sm p-6 border border-gray-100">
        <h2 class="font-semibold mb-4" style="color:#191970;">
            <i class="fas fa-upload mr-2" style="color:#9400D3;"></i>
            Upload Documents
        </h2>

        <form method="POST" action="{{ route('pharmacy.requirements.store') }}" enctype="multipart/form-data">
            @csrf

            {{-- Validation errors --}}
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-5">
                <label class="block text-sm font-medium mb-2" style="color:#191970;">
                    Select Files <span class="text-red-500">*</span>
                </label>
                <div class="border-2 border-dashed rounded-xl p-6 text-center transition-colors"
                    style="border-color:rgba(148,0,211,0.3);background:rgba(148,0,211,0.03);"
                    id="dropZone">
                    <i class="fas fa-cloud-arrow-up text-3xl mb-2" style="color:#9400D3;opacity:0.6;"></i>
                    <p class="text-sm text-gray-500 mb-3">Drag & drop files here or click to browse</p>
                    <input
                        type="file"
                        name="requirements[]"
                        id="requirementsInput"
                        multiple
                        accept=".jpg,.jpeg,.png,.pdf"
                        class="hidden"
                    >
                    <button type="button"
                        onclick="document.getElementById('requirementsInput').click()"
                        class="px-5 py-2 rounded-full text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        style="background:#9400D3;">
                        Browse Files
                    </button>
                    <p id="fileNames" class="text-xs text-gray-500 mt-3"></p>
                </div>
                <p class="text-xs text-gray-400 mt-2">You can select multiple files at once. JPG, PNG, and PDF only.</p>
            </div>

            <button type="submit"
                class="w-full py-3 rounded-full font-bold text-sm transition-opacity hover:opacity-90 flex items-center justify-center gap-2"
                style="background:#191970;color:#D9F855;">
                <i class="fas fa-paper-plane"></i>
                Submit Requirements
            </button>
        </form>
    </div>

    {{-- Back link --}}
    <div class="mt-6 text-center">
        <a href="{{ route('pharmacy.profile.edit') }}" class="text-sm hover:underline" style="color:#9400D3;">
            <i class="fas fa-arrow-left mr-1"></i> Back to Profile
        </a>
    </div>

</div>
</div>

<script>
document.getElementById('requirementsInput').addEventListener('change', function() {
    const names = Array.from(this.files).map(f => f.name).join(', ');
    document.getElementById('fileNames').textContent = names
        ? this.files.length + ' file(s) selected: ' + names
        : '';
});
</script>
@endsection
