<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PharmacyProfileController extends Controller
{
    /**
     * Session key used to carry a location picked on the separate map page
     * back to the profile edit form.
     */
    private const LOCATION_KEY = 'pharmacy_profile.location';

    public function edit(Request $request)
    {
        $user = auth()->user();
        $pharmacy = Pharmacy::where('user_id', $user->id)->first();

        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned to your account.');
        }

        // A freshly-picked location (from the map page) takes precedence over
        // the persisted pharmacy values for pre-filling the form. It is only
        // persisted once the profile form itself is submitted.
        $location = $request->session()->pull(self::LOCATION_KEY);

        return view('pharmacy.profile_edit', compact('pharmacy', 'user', 'location'));
    }

    /**
     * Display the separate location picker map page for the profile flow.
     */
    public function locationEdit(Request $request)
    {
        $user = auth()->user();
        $pharmacy = Pharmacy::where('user_id', $user->id)->first();

        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned to your account.');
        }

        $location = $request->session()->get(self::LOCATION_KEY, [
            'latitude' => $pharmacy->latitude,
            'longitude' => $pharmacy->longitude,
            'address' => null,
        ]);

        return view('pharmacy.location_edit', compact('pharmacy', 'location'));
    }

    /**
     * Handle "Save Location" from the map page: validate the coordinate ranges,
     * store the picked location in the session, and redirect back to the
     * profile edit form. This does NOT persist to the pharmacy record; the
     * profile form submission does that via update().
     */
    public function storeLocation(Request $request)
    {
        $user = auth()->user();
        $pharmacy = Pharmacy::where('user_id', $user->id)->first();

        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned to your account.');
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

        return redirect()->route('pharmacy.profile.edit');
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $pharmacy = Pharmacy::where('user_id', $user->id)->first();

        if (! $pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned to your account.');
        }

        $data = $request->validate([
            'pharmacy_name' => 'required|string|max:255',
            'pharmacyAddress' => 'nullable|string|max:255',
            'contactNumber' => 'nullable|string|max:50',
            'operating_hours' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($pharmacy->logo_path) {
                Storage::disk('public')->delete($pharmacy->logo_path);
            }
            $path = $request->file('logo')->store('pharmacy-logos', 'public');
            $pharmacy->logo_path = $path;
        }

        // Remove logo from data array so update() doesn't overwrite what we set manually
        unset($data['logo']);

        $pharmacy->fill($data);
        $pharmacy->save();

        // Update the linked user email if provided and it changed.
        if (! empty($data['email']) && $data['email'] !== $user->email) {
            $user->email = $data['email'];
            $user->save();
        }

        return redirect()->route('pharmacy.profile.edit')->with('success', 'Pharmacy profile updated.');
    }
}
