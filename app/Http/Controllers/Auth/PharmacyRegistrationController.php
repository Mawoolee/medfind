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

class PharmacyRegistrationController extends Controller
{
    /**
     * Session key used to carry step 1 (account) data forward to step 2.
     */
    private const SESSION_KEY = 'pharmacy_registration.account';

    /**
     * Session key used to carry the picked location from the map page back to
     * the step 2 details form.
     */
    private const LOCATION_KEY = 'pharmacy_registration.location';

    /**
     * Display step 1 of pharmacy registration (account details).
     */
    public function create(): View
    {
        return view('auth.pharmacy.register-account');
    }

    /**
     * Handle step 1: validate account details and carry them forward in the
     * session. The user is NOT created yet.
     *
     * @throws ValidationException
     */
    public function storeAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $request->session()->put(self::SESSION_KEY, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('register.pharmacy.details');
    }

    /**
     * Display step 2 of pharmacy registration (pharmacy details).
     * Redirects back to step 1 if step 1 data is not present in the session.
     */
    public function details(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            return redirect()->route('register.pharmacy');
        }

        return view('auth.pharmacy.register-details', [
            'location' => $request->session()->get(self::LOCATION_KEY),
        ]);
    }

    /**
     * Display the separate location picker map page for the registration flow.
     * Guarded the same way as details(): step 1 data must exist in the session.
     */
    public function locationPicker(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            return redirect()->route('register.pharmacy');
        }

        return view('auth.pharmacy.register-location', [
            'location' => $request->session()->get(self::LOCATION_KEY),
        ]);
    }

    /**
     * Handle "Save Location" from the map page: validate the coordinate ranges,
     * store the picked location in the session, and redirect back to step 2.
     * This does NOT create the user/pharmacy.
     *
     * @throws ValidationException
     */
    public function storeLocation(Request $request): RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            return redirect()->route('register.pharmacy');
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $request->session()->put(self::LOCATION_KEY, [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'address' => $validated['address'] ?? null,
        ]);

        return redirect()->route('register.pharmacy.details');
    }

    /**
     * Handle step 2: validate pharmacy details, create the user and pharmacy,
     * fire the Registered event, log in, and redirect to the requirements page.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $account = $request->session()->get(self::SESSION_KEY);

        if (! $account) {
            return redirect()->route('register.pharmacy');
        }

        $validated = $request->validate([
            'pharmacy_name' => ['required', 'string', 'max:255'],
            'pharmacyAddress' => ['required', 'string', 'max:500'],
            'contactNumber' => ['nullable', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $user = User::create([
            'name' => $account['name'],
            'email' => $account['email'],
            'password' => $account['password'],
            'role' => 'pharmacy',
        ]);

        Pharmacy::create([
            'pharmacy_name' => $validated['pharmacy_name'],
            'pharmacyAddress' => $validated['pharmacyAddress'],
            'contactNumber' => $validated['contactNumber'] ?? null,
            'latitude' => isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            'longitude' => isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $request->session()->forget([self::SESSION_KEY, self::LOCATION_KEY]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('pharmacy.requirements');
    }
}
