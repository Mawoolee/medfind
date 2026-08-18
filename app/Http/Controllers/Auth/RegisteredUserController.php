<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $role = $request->input('role', 'consumer');

        $rules = [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role'     => ['required', 'in:consumer,pharmacy'],
        ];

        if ($role === 'pharmacy') {
            $rules['pharmacy_name']    = ['required', 'string', 'max:255'];
            $rules['pharmacyAddress']  = ['required', 'string', 'max:500'];
            $rules['contactNumber']    = ['nullable', 'string', 'max:50'];
            $rules['latitude']         = ['nullable', 'numeric', 'between:-90,90'];
            $rules['longitude']        = ['nullable', 'numeric', 'between:-180,180'];
        }

        $request->validate($rules);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $role === 'pharmacy' ? 'pharmacy' : 'consumer',
        ]);

        if ($role === 'pharmacy') {
            Pharmacy::create([
                'pharmacy_name'   => $request->pharmacy_name,
                'pharmacyAddress' => $request->pharmacyAddress,
                'contactNumber'   => $request->filled('contactNumber') ? $request->contactNumber : null,
                'latitude'        => $request->filled('latitude')  ? (float) $request->latitude  : null,
                'longitude'       => $request->filled('longitude') ? (float) $request->longitude : null,
                'user_id'         => $user->id,
                'status'          => 'pending',
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        if ($role === 'pharmacy') {
            return redirect()->route('pharmacy.requirements');
        }

        return redirect()->route('home');
    }
}
