<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'MedFind') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- MedFind Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/medfind.css') }}">

    <!-- Scripts - Use CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f0ff;
            overflow: hidden;
            height: 100vh;
        }
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 4px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(148, 0, 211, 0.05);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(148, 0, 211, 0.3);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 0, 211, 0.5);
        }
        
        /* Fix for navigation to stay on top */
        .navigation-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10000;
        }
    </style>
</head>
<body>
    <div class="h-screen overflow-hidden">
        <!-- Navigation with fixed position -->
        <div class="navigation-wrapper">
            @include('layouts.navigation')
        </div>

        <main class="h-full pt-16">
            @yield('content')
        </main>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- MedFind Custom JS -->
    <script src="{{ asset('js/medfind.js') }}"></script>
</body>
</html>