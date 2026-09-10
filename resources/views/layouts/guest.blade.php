<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- For development only - remove this in production -->
        @if(app()->environment('local'))
            <script src="https://cdn.tailwindcss.com"></script>
        @endif

        <!-- Dark Mode Init (runs before render to prevent flash) -->
        <script>
            (function() {
                if (localStorage.getItem('medfind-theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                min-height: 100vh;
                background-image: url('/images/MedFind Background light.png');
                background-size: cover;
                background-position: center;
                background-attachment: fixed;
                background-color: #f0f0ff;
            }
            /* Light mode overlay - subtle, lets background show through */
            body::before {
                content: '';
                position: fixed;
                inset: 0;
                background: rgba(20, 20, 30, 0.25);
                pointer-events: none;
                z-index: 0;
            }
            body > div {
                position: relative;
                z-index: 1;
            }
            
            /* Dark mode background & overlay */
            html.dark body {
                background-image: url('/images/MedFind Background login.png');
                background-color: #0a0f3d;
            }
            html.dark body::before {
                background: rgba(5, 10, 40, 0.60);
            }
        </style>
    </head>
    <body>
                <!-- Floating Dark Mode Toggle -->
        <button type="button" id="darkModeToggle"
            onclick="toggleDarkMode()"
            class="fixed top-4 right-4 z-50 flex items-center justify-center w-12 h-12 rounded-full bg-white/10 dark:bg-white/20 backdrop-blur-md border border-white/20 dark:border-white/30 text-white dark:text-gray-800 hover:bg-white/20 dark:hover:bg-white/30 transition shadow-lg"
            title="Toggle dark/light mode"
            aria-label="Toggle dark/light mode">
            <i id="darkModeIcon" class="fas fa-moon text-lg"></i>
        </button>
<div class="min-h-screen">
            {{ $slot }}
        </div>
    
    <!-- Dark Mode Toggle Script -->
    <script>
        function toggleDarkMode() {
            const html = document.documentElement;
            const icon = document.getElementById('darkModeIcon');
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('medfind-theme', 'light');
                if (icon) { icon.classList.replace('fa-sun', 'fa-moon'); }
            } else {
                html.classList.add('dark');
                localStorage.setItem('medfind-theme', 'dark');
                if (icon) { icon.classList.replace('fa-moon', 'fa-sun'); }
            }
        }
        // Set correct icon on load
        document.addEventListener('DOMContentLoaded', function() {
            const icon = document.getElementById('darkModeIcon');
            if (icon && document.documentElement.classList.contains('dark')) {
                icon.classList.replace('fa-moon', 'fa-sun');
            }
        });
    </script>
</body>
</html>