<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MedFind') }}</title>
    
    <!-- Simple CSS (no Vite) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-primary { background: #667eea; border: none; }
        .btn-primary:hover { background: #5a67d8; }
    </style>
</head>
<body>
    <div class="min-vh-100">
        @include('layouts.navigation')
        
        <main>
            {{ $slot }}
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>