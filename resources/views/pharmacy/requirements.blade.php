@extends('layouts.app')

@section('title', 'Upload Business Requirements')

@section('content')
<div class="min-h-screen" style="background:#f0f0ff;"> 
<div class="container mx-auto px-4 max-w-3xl" style="padding-top:60px;padding-bottom:40px;">

 <div class="flex items-center gap-3 mb-6">
 <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" style="background:#191970;">
 <i class="fas fa-file-medical text-white text-lg"></i>
 </div>
 <div> 
 <h1 class="text-2xl font-bold" style="color:#191970;">Upload Business Requirements</h1>
 <p class="text-sm text-gray-500">Complete your pharmacy registration by submitting the required documents</p>
 </div>
 </div>

 <div class="flex items-center gap-2 mb-6">
 <span class="text-sm font-medium text-gray-600">Current Status:</span>
 @php
 $statusColors = ['pending'=>'bg-amber-100 text-amber-800 border-amber-300','approved'=>'bg-green-100 text-green-800 border-green-300','rejected'=>'bg-red-100 text-red-800 border-red-300'];
 $statusColor = $statusColors[$pharmacy->status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
 @endphp
 <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $statusColor }}">{{ ucfirst($pharmacy->status) }}</span>
 </div>

 @if(session('success'))
 <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-xl mb-5 flex items-center gap-2 text-sm">
 <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
 </div>
 @endif
 @if(session('error'))
 <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-xl mb-5 flex items-center gap-2 text-sm">
 <i class="fas fa-exclamation-circle text-red-500"></i> {{ session('error') }}
 </div>
 @endif
 @if(session('info'))
 <div class="bg-blue-50 border border-blue-300 text-blue-800 px-4 py-3 rounded-xl mb-5 flex items-center gap-2 text-sm">
 <i class="fas fa-info-circle text-blue-500"></i> {{ session('info') }}
 </div>
 @endif

 {{-- Checklist --}}
 <div class="bg-white rounded-[20px] shadow-sm p-5 mb-6 border border-gray-100">
 <h2 class="font-semibold mb-1 text-sm" style="color:#191970;"><i class="fas fa-list-check mr-2" style="color:#9400D3;"></i>Required Documents</h2>
 <p class="text-xs text-gray-400 mb-4">Checked items mean a file has already been uploaded.</p>
 <div class="grid grid-cols-2 gap-2">
 @foreach($docs as $key => $doc)
 @php $isUploaded = !empty($uploaded[$key]); @endphp
 <input type="hidden" id="sv_{{ $key }}" value="{{ $isUploaded ? 1 : 0 }}">
 <div id="checkrow_{{ $key }}" class="flex items-center gap-2.5 py-2 px-3 rounded-xl" style="background:{{ $isUploaded ? 'rgba(25,25,112,0.05)' : 'rgba(148,0,211,0.03)' }};">
 <span id="check_{{ $key }}" class="w-5 h-5 rounded flex items-center justify-center flex-shrink-0 border-2"
 style="{{ $isUploaded ? 'background:#191970;border-color:#191970;' : ($doc['required'] ? 'border-color:#9400D3;' : 'border-color:#d1d5db;') }}">
 @if($isUploaded)<i class="fas fa-check text-[8px] text-white"></i>@endif
 </span>
 <span id="checklabel_{{ $key }}" class="text-xs flex-1 truncate" style="color:{{ $isUploaded ? '#191970' : '#374151' }};font-weight:{{ $isUploaded ? '600' : '400' }};">
 {{ $doc['label'] }}@if(!$doc['required'])<span class="text-gray-400 font-normal ml-1">(optional)</span>@endif
 </span>
 </div>
 @endforeach
 </div>
 </div>

 {{-- Upload form --}}
 <form method="POST" action="{{ route('pharmacy.requirements.store') }}" enctype="multipart/form-data">
 @csrf
 @if($errors->any())
 <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm">
 <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
 </div>
 @endif

 <div class="grid grid-cols-2 gap-4">
 @foreach($docs as $key => $doc)
 @php $isUploaded = !empty($uploaded[$key]); @endphp
 <div class="bg-white rounded-[16px] shadow-sm border overflow-hidden"
 style="border-color:{{ $isUploaded ? 'rgba(25,25,112,0.2)' : 'rgba(148,0,211,0.12)' }};">
 <div class="flex items-center gap-2 px-4 py-2.5 border-b"
 style="background:{{ $isUploaded ? 'rgba(25,25,112,0.05)' : 'rgba(148,0,211,0.03)' }};border-color:{{ $isUploaded ? 'rgba(25,25,112,0.1)' : 'rgba(148,0,211,0.08)' }};">
 <span class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0"
 style="background:{{ $isUploaded ? '#191970' : 'rgba(148,0,211,0.12)' }};">
 <i class="fas {{ $isUploaded ? 'fa-check' : 'fa-file' }} text-[8px]"
 style="color:{{ $isUploaded ? '#D9F855' : '#9400D3' }};"></i>
 </span>
 <span class="text-xs font-semibold flex-1 truncate" style="color:#191970;" title="{{ $doc['label'] }}">{{ $doc['label'] }}</span>
 @if($isUploaded)
 <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full text-white flex-shrink-0" style="background:#191970;">&#x2713;</span>
 @else
 <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0 border"
 style="{{ $doc['required'] ? 'color:#b45309;background:#fffbeb;border-color:#fcd34d;' : 'color:#9ca3af;background:#f9fafb;border-color:#e5e7eb;' }}">
 {{ $doc['required'] ? 'Required' : 'Optional' }}
 </span>
 @endif
 </div>
 <div class="px-4 py-3 flex items-center gap-3">
 <input type="file" name="doc_{{ $key }}" id="file_{{ $key }}" accept=".jpg,.jpeg,.png,.pdf" class="hidden" onchange="showFileName('{{ $key }}', this)">
 <button type="button" onclick="document.getElementById('file_{{ $key }}').click()"
 class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold text-white hover:opacity-90 transition"
 style="background:#9400D3;">
 <i class="fas fa-upload text-[9px]"></i> Select File
 </button>
 <p id="fname_{{ $key }}" class="text-xs truncate flex-1 min-w-0">
 @if($isUploaded)
 <span style="color:#191970;font-weight:600;"><i class="fas fa-file mr-1"></i>Uploaded</span>
 @else
 <span class="text-gray-400">No file chosen</span>
 @endif
 </p>
 </div>
 @error("doc_{{ $key }}")<p class="px-4 pb-2 text-red-500 text-xs">{{ $message }}</p>@enderror
 </div>
 @endforeach
 </div>

 <button type="submit" class="w-full mt-6 py-3 rounded-full font-bold text-sm hover:opacity-90 transition flex items-center justify-center gap-2" style="background:#191970;color:#D9F855;">
 <i class="fas fa-paper-plane"></i> Submit Requirements
 </button>
 </form>

 <div class="mt-5 text-center">
 <a href="{{ route('pharmacy.profile.edit') }}" class="text-sm hover:underline" style="color:#9400D3;">
 <i class="fas fa-arrow-left mr-1"></i> Back to Profile
 </a>
 </div>
</div>
</div>

<script>
var DOC_KEYS = ["bir","business","philhealth","fda","pharmacist"];
var SESSION_PREFIX = "mf_req_";

function checkItem(key) {
 var box = document.getElementById("check_" + key);
 var row = document.getElementById("checkrow_" + key);
 var lbl = document.getElementById("checklabel_" + key);
 if (!box) return;
 box.innerHTML = '<i class="fas fa-check" style="font-size:8px;color:#fff;"></i>';
 box.style.cssText = "background:#191970;border-color:#191970;width:20px;height:20px;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;flex-shrink:0;";
 if (row) row.style.background = "rgba(25,25,112,0.05)";
 if (lbl) { lbl.style.color = "#191970"; lbl.style.fontWeight = "600"; }
}

function showFileName(key, input) {
 var label = document.getElementById("fname_" + key);
 if (!input.files || !input.files[0]) return;
 var file = input.files[0];
 if (label) label.innerHTML = '<i class="fas fa-file" style="color:#9400D3;margin-right:4px;"></i><span style="color:#191970;font-weight:600;">' + file.name + '</span>';
 checkItem(key);
 var reader = new FileReader();
 reader.onload = function(e) {
 try {
 sessionStorage.setItem(SESSION_PREFIX + key + "_name", file.name);
 sessionStorage.setItem(SESSION_PREFIX + key + "_type", file.type);
 sessionStorage.setItem(SESSION_PREFIX + key + "_data", e.target.result);
 } catch(ex) {}
 };
 reader.readAsDataURL(file);
}

function restoreFiles() {
 DOC_KEYS.forEach(function(key) {
 var sv = document.getElementById("sv_" + key);
 if (sv && sv.value === "1") return;
 var name = sessionStorage.getItem(SESSION_PREFIX + key + "_name");
 var data = sessionStorage.getItem(SESSION_PREFIX + key + "_data");
 if (!name || !data) return;
 try {
 var arr = data.split(","), mime = arr[0].match(/:(.*?);/)[1];
 var bstr = atob(arr[1]), n = bstr.length, u8 = new Uint8Array(n);
 while (n--) u8[n] = bstr.charCodeAt(n);
 var f = new File([u8], name, { type: mime });
 var inp = document.getElementById("file_" + key);
 if (inp) { var dt = new DataTransfer(); dt.items.add(f); inp.files = dt.files; }
 var lbl = document.getElementById("fname_" + key);
 if (lbl) lbl.innerHTML = '<i class="fas fa-file" style="color:#9400D3;margin-right:4px;"></i><span style="color:#191970;font-weight:600;">' + name + '</span>';
 checkItem(key);
 } catch(ex) {
 sessionStorage.removeItem(SESSION_PREFIX + key + "_name");
 sessionStorage.removeItem(SESSION_PREFIX + key + "_data");
 sessionStorage.removeItem(SESSION_PREFIX + key + "_type");
 }
 });
}

document.addEventListener("DOMContentLoaded", function() {
 restoreFiles();
 var form = document.querySelector("form");
 if (form) form.addEventListener("submit", function() {
 DOC_KEYS.forEach(function(key) {
 sessionStorage.removeItem(SESSION_PREFIX + key + "_name");
 sessionStorage.removeItem(SESSION_PREFIX + key + "_data");
 sessionStorage.removeItem(SESSION_PREFIX + key + "_type");
 });
 });
});
</script>
@endsection