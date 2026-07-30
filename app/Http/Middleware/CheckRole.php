<?php
// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $userRole = auth()->user()->role;

        // Check if user role matches any of the allowed roles
        foreach ($roles as $role) {
            if ($userRole === $role) {
                return $next($request);
            }
        }

        // Redirect to appropriate dashboard based on role
        if ($userRole === 'admin') {
            return redirect()->route('admin.dashboard');
        } elseif ($userRole === 'pharmacy') {
            return redirect()->route('pharmacy.dashboard');
        } else {
            return redirect()->route('consumer.dashboard');
        }
    }
}