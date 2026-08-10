@extends('layouts.app')

@section('title', 'Thank You — MedFind Survey')

@section('content')
<div class="container mx-auto px-4 py-20 max-w-lg text-center">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12">
        <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-check-circle text-green-500 text-4xl"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-[#191970] mb-3">Thank You!</h1>
        <p class="text-gray-500 text-sm mb-6">
            Your evaluation has been recorded. Your feedback helps us improve
            MedFind and better serve the people of Legazpi City.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('home') }}"
               class="bg-[#191970] text-[#D9F855] px-6 py-2.5 rounded-full text-sm font-bold hover:bg-[#2a2a8a] transition">
                <i class="fas fa-map-marker-alt mr-2"></i>Back to Map
            </a>
            <a href="{{ route('survey.show') }}"
               class="border border-gray-300 text-gray-600 px-6 py-2.5 rounded-full text-sm font-semibold hover:bg-gray-50 transition">
                Submit Another Response
            </a>
        </div>
    </div>
</div>
@endsection
