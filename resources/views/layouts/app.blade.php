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
    <!-- Leaflet Routing Machine CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

    <!-- MedFind Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/medfind.css') }}">

    <!-- Vite for Tailwind CSS (para sa production-ready) -->

    <script>
    // Pusher guard early: place before any bundled scripts so inlined/compiled code won't throw when no key is present
    (function(){
        try {
            var _pusherKey = "{{ env('PUSHER_APP_KEY', '') }}";
            if (!_pusherKey) {
                window.Pusher = function() { console.debug && console.debug('Pusher stub used (no app key)'); };
                window.Pusher.prototype = {};
            }
        } catch(e) {}
    })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

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

        /* Fallback kung walang Vite (development mode) */
        @if(!app()->environment('production'))
            /* Temporary Tailwind CDN for development */
            /* Remove this in production */
        @endif
    </style>

    <!-- For development only - remove this in production -->
    @if(app()->environment('local'))
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body>
<div class="h-screen overflow-hidden">
        <!-- Navigation with fixed position -->
        <div class="navigation-wrapper">
            @include('layouts.navigation')
        </div>

        <main class="h-full pt-16 overflow-y-auto">
            @yield('content')
        </main>
    </div>

<!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Routing Machine JS -->
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <!-- Alpine.js for interactive dropdowns -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- MedFind Custom JS -->
    <script>
    // Pusher guard: if no PUSHER_APP_KEY is configured, install a harmless stub so pages that call Pusher won't throw.
    (function(){
        try {
            var _pusherKey = "{{ env('PUSHER_APP_KEY', '') }}";
            if (!_pusherKey) {
                // Minimal stub that mimics Pusher constructor so code calling `new Pusher()` doesn't throw.
                window.Pusher = function() { console.debug && console.debug('Pusher stub used (no app key)'); };
                window.Pusher.prototype = {};
            }
        } catch(e) { /* swallow */ }
    })();
    </script>
    <script src="{{ asset('js/medfind.js') }}"></script>

    @stack('scripts')
</body>
</html>
