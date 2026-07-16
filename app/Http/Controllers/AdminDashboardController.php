<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Message;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users'      => User::count(),
            'pharmacies' => Pharmacy::count(),
            'medicines'  => Medicine::count(),
            'searches'   => InventoryItem::sum('stock_quantity'),
        ];

        $recentUsers     = User::latest()->take(5)->get();
        $recentPharmacies = Pharmacy::latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentUsers', 'recentPharmacies'));
    }

    // ── Users ─────────────────────────────────────────────────────────────────
    public function users()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        return response()->json($users);
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role'  => 'required|in:consumer,pharmacy_operator,admin',
        ]);

        $user->update($request->only('name', 'email', 'role'));
        return response()->json(['success' => true, 'user' => $user]);
    }

    public function destroyUser(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot delete yourself.');
        $user->delete();
        return response()->json(['success' => true]);
    }

    // ── Pharmacies ────────────────────────────────────────────────────────────
    public function pharmacies()
    {
        $pharmacies = Pharmacy::with('user')->orderBy('created_at', 'desc')->get();
        return response()->json($pharmacies);
    }

    public function storePharmacy(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'address'        => 'required|string|max:500',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
            'contact_number' => 'nullable|string|max:50',
        ]);

        $pharmacy = Pharmacy::create([
            'name'           => $request->name,
            'address'        => $request->address,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'contact_number' => $request->contact_number,
            'status'         => 'approved',
        ]);

        return response()->json(['success' => true, 'pharmacy' => $pharmacy]);
    }

    public function updatePharmacy(Request $request, Pharmacy $pharmacy)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'address'        => 'required|string|max:500',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
            'contact_number' => 'nullable|string|max:50',
            'status'         => 'required|in:pending,approved,rejected',
        ]);

        $pharmacy->update($request->only('name', 'address', 'latitude', 'longitude', 'contact_number', 'status'));
        return response()->json(['success' => true, 'pharmacy' => $pharmacy]);
    }

    public function destroyPharmacy(Pharmacy $pharmacy)
    {
        $pharmacy->delete();
        return response()->json(['success' => true]);
    }

    public function approvePharmacy(Pharmacy $pharmacy)
    {
        $pharmacy->update(['status' => 'approved']);
        return response()->json(['success' => true]);
    }

    // ── Medicines ─────────────────────────────────────────────────────────────
    public function medicines()
    {
        $medicines = Medicine::orderBy('name')->get();
        return response()->json($medicines);
    }

    public function storeMedicine(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'generic_name'          => 'nullable|string|max:255',
            'dosage_form'           => 'nullable|string|max:100',
            'category'              => 'nullable|string|max:100',
            'description'           => 'nullable|string',
            'requires_prescription' => 'boolean',
        ]);

        $medicine = Medicine::create($request->all());
        return response()->json(['success' => true, 'medicine' => $medicine]);
    }

    public function updateMedicine(Request $request, Medicine $medicine)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'generic_name'          => 'nullable|string|max:255',
            'dosage_form'           => 'nullable|string|max:100',
            'category'              => 'nullable|string|max:100',
            'description'           => 'nullable|string',
            'requires_prescription' => 'boolean',
        ]);

        $medicine->update($request->all());
        return response()->json(['success' => true, 'medicine' => $medicine]);
    }

    public function destroyMedicine(Medicine $medicine)
    {
        $medicine->delete();
        return response()->json(['success' => true]);
    }
}
