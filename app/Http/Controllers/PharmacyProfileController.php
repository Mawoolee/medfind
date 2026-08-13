<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PharmacyProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $pharmacy = Pharmacy::where('user_id', $user->id)->first();

        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned to your account.');
        }

        return view('pharmacy.profile_edit', compact('pharmacy', 'user'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $pharmacy = Pharmacy::where('user_id', $user->id)->first();

        if (!$pharmacy) {
            return redirect()->back()->with('error', 'No pharmacy assigned to your account.');
        }

        $data = $request->validate([
            'pharmacy_name'   => 'required|string|max:255',
            'pharmacyAddress' => 'nullable|string|max:255',
            'contactNumber'   => 'nullable|string|max:50',
            'operating_hours' => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'latitude'        => 'nullable|numeric',
            'longitude'       => 'nullable|numeric',
            'logo'            => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
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
        if (!empty($data['email']) && $data['email'] !== $user->email) {
            $user->email = $data['email'];
            $user->save();
        }

        return redirect()->route('pharmacy.profile.edit')->with('success', 'Pharmacy profile updated.');
    }
}