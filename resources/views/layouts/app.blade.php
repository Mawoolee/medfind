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

    <!-- MedFind Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/medfind.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pharmacy-info-window.css?v=37') }}">

    
    <!-- Dark Mode Init (runs before render to prevent flash) -->
    <script>
        (function() {
            if (localStorage.getItem('medfind-theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <!-- Vite for Tailwind CSS (para sa production-ready) -->

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f0ff;
            overflow: hidden;
            height: 100vh;
        }
        /* Mobile: relax the fixed app-shell so tall content scrolls naturally
           (the fixed top nav + pt-16 offset are preserved by the markup). */
        @media (max-width: 639px) {
            body {
                overflow: hidden;
                overflow-y: auto;
                height: auto;
                min-height: 100vh;
                min-height: 100dvh;
            }
            .app-shell {
                height: auto !important;
                min-height: 100vh;
                min-height: 100dvh;
                overflow: visible !important;
            }
            .app-main {
                height: auto !important;
                min-height: calc(100dvh - 4rem);
                overflow: visible !important;
            }
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
<div class="app-shell h-screen overflow-hidden">
        <!-- Navigation with fixed position -->
        <div class="navigation-wrapper">
            @include('layouts.navigation')
        </div>

        <main class="app-main h-full pt-16 overflow-y-auto">
            @yield('content')
        </main>
    </div>

    {{-- Alpine.js is bundled via resources/js/app.js (@vite above). Do not load it
         again from a CDN: two Alpine instances initialize the same x-data
         components and swallow the first click. --}}

    <!-- MedFind Custom JS -->
    <script src="{{ asset('js/medfind-google.js?v=39') }}"></script>

    <!-- Google Maps Initialization Callback -->
    <script>
        function initGoogleMaps() {
            if (typeof window.initializeMap === 'function') {
                window.initializeMap();
            }
        }
    </script>

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places&callback=initGoogleMaps"></script>

    @stack('scripts')

    <!-- Dark Mode Toggle Script -->
    <script>
        function updateToggleStyle() {
            const btn = document.getElementById('darkModeToggle');
            if (!btn) return;
            if (document.documentElement.classList.contains('dark')) {
                btn.style.backgroundColor = 'rgba(255,255,255,0.2)';
                btn.style.color = 'white';
                btn.style.borderColor = 'rgba(255,255,255,0.3)';
            } else {
                btn.style.backgroundColor = '#e5e7eb';
                btn.style.color = '#374151';
                btn.style.borderColor = '#d1d5db';
            }
        }
        function toggleDarkMode() {
            const html = document.documentElement;
            const icon = document.getElementById('darkModeIcon');
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('medfind-theme', 'light');
                if (icon) { icon.classList.remove('fa-sun'); icon.classList.add('fa-moon'); }
            } else {
                html.classList.add('dark');
                localStorage.setItem('medfind-theme', 'dark');
                if (icon) { icon.classList.remove('fa-moon'); icon.classList.add('fa-sun'); }
            }
            updateToggleStyle();
        }
        // Set correct icon on load
        document.addEventListener('DOMContentLoaded', function() {
            const icon = document.getElementById('darkModeIcon');
            if (icon && document.documentElement.classList.contains('dark')) {
                icon.classList.replace('fa-moon', 'fa-sun');
            }
            updateToggleStyle();
        });
    </script>
</body>
</html>
