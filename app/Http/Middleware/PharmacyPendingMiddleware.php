<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Pharmacy;

class PharmacyPendingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['pharmacy', 'pharmacy_operator'])) {
            return $next($request);
        }

        $pharmacy = Pharmacy::where('user_id', $user->id)->first();
        if (!$pharmacy) return $next($request);

        // Pending pharmacies can ONLY access profile and requirements routes
        $allowedRouteNames = [
            'pharmacy.profile.edit',
            'pharmacy.profile.update',
            'pharmacy.profile.location',
            'pharmacy.profile.location.store',
            'pharmacy.requirements',
            'pharmacy.requirements.store',
            'logout',
        ];

        if ($pharmacy->status === 'pending' && !in_array($request->route()?->getName(), $allowedRouteNames)) {
            return redirect()->route('pharmacy.requirements')->with('info', 'Your account is pending approval. Please complete your requirements first.');
        }

        return $next($request);
    }
}