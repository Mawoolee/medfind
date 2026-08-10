<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Message;
use App\Models\Pharmacy;
use App\Models\User;
use App\Notifications\PharmacyStatusNotification;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $userCount = User::count();
        $pharmacyCount = Pharmacy::count();
        $medicineCount = Medicine::count();
        $messageCount = Message::count();

        $recentUsers = User::latest()->take(5)->get();
        $recentPharmacies = Pharmacy::latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'userCount',
            'pharmacyCount',
            'medicineCount',
            'messageCount',
            'recentUsers',
            'recentPharmacies'
        ));
    }

    // ── Activity Logging Helper ───────────────────────────────────────────────
    protected function logActivity(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
    {
        ActivityLog::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details,
        ]);
    }

    // ── Users ─────────────────────────────────────────────────────────────────
    public function users(Request $request)
    {
        $query = User::query();

        // Search filter
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        // Role filter
        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('admin.users', compact('users'));
    }

public function editUser(User $user): View
    {
        return view('admin.edit-user', compact('user'));
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role'  => 'required|in:consumer,pharmacy,pharmacy_operator,admin',
        ]);

        $user->update($request->only('name', 'email', 'role'));
        $this->logActivity('updated', 'User', $user->id, "Updated user {$user->name} ({$user->email})");
        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function deleteUser(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot delete yourself.');
        $name = $user->name;
        $user->delete();
        $this->logActivity('deleted', 'User', null, "Deleted user {$name}");
        return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
    }

    // ── Pharmacies ────────────────────────────────────────────────────────────
    public function pharmacies(Request $request)
    {
        $query = Pharmacy::with('user');

        // Search filter
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('pharmacy_name', 'like', "%{$term}%")
                  ->orWhere('pharmacyAddress', 'like', "%{$term}%");
            });
        }

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $pharmacies = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('admin.pharmacies', compact('pharmacies'));
    }

    public function addPharmacy(): View
    {
        $users = User::whereIn('role', ['pharmacy', 'pharmacy_operator'])->orderBy('name')->get();
        return view('admin.add-pharmacy', compact('users'));
    }

    public function logs(): View
    {
        $logPath = storage_path('logs/laravel.log');
        $logs = [];

        if (file_exists($logPath)) {
            $contents = collect(array_slice(explode("\n", file_get_contents($logPath)), -100));
            $logs = $contents->filter()->values();
        }

        return view('admin.logs', compact('logs'));
    }

    public function storePharmacy(Request $request)
    {
        $request->validate([
            'pharmacy_name'   => 'required|string|max:255',
            'pharmacyAddress' => 'required|string|max:500',
            'latitude'        => 'nullable|numeric',
            'longitude'       => 'nullable|numeric',
            'contactNumber'   => 'nullable|string|max:50',
            'user_id'         => 'nullable|exists:users,id',
        ]);

        $pharmacy = Pharmacy::create([
            'pharmacy_name'   => $request->pharmacy_name,
            'pharmacyAddress' => $request->pharmacyAddress,
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
            'contactNumber'   => $request->contactNumber,
            'user_id'         => $request->user_id,
            'status'          => 'approved',
        ]);

        $this->logActivity('created', 'Pharmacy', $pharmacy->id, "Created pharmacy {$pharmacy->pharmacy_name}");
        return redirect()->route('admin.pharmacies')->with('success', 'Pharmacy added successfully.');
    }

public function editPharmacy(Pharmacy $pharmacy): View
    {
        $users = User::whereIn('role', ['pharmacy', 'pharmacy_operator'])->orderBy('name')->get();
        return view('admin.edit-pharmacy', compact('pharmacy', 'users'));
    }

    public function updatePharmacy(Request $request, Pharmacy $pharmacy)
    {
        $request->validate([
            'pharmacy_name'   => 'required|string|max:255',
            'pharmacyAddress' => 'required|string|max:500',
            'latitude'        => 'nullable|numeric',
            'longitude'       => 'nullable|numeric',
            'contactNumber'   => 'nullable|string|max:50',
            'user_id'         => 'nullable|exists:users,id',
            'status'          => 'required|in:pending,approved,rejected',
        ]);

        $oldStatus = $pharmacy->status;

        $pharmacy->update([
            'pharmacy_name'   => $request->pharmacy_name,
            'pharmacyAddress' => $request->pharmacyAddress,
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
            'contactNumber'   => $request->contactNumber,
            'user_id'         => $request->user_id,
            'status'          => $request->status,
        ]);

        $this->logActivity('updated', 'Pharmacy', $pharmacy->id, "Updated pharmacy {$pharmacy->pharmacy_name}");

        // Notify owner when status changes
        if ($oldStatus !== $request->status && $pharmacy->user) {
            $pharmacy->user->notify(new PharmacyStatusNotification($pharmacy, $request->status));
            $this->logActivity($request->status, 'Pharmacy', $pharmacy->id, "Pharmacy {$pharmacy->pharmacy_name} {$request->status}");
        }

        return redirect()->route('admin.pharmacies')->with('success', 'Pharmacy updated successfully.');
    }

    public function deletePharmacy(Pharmacy $pharmacy)
    {
        $name = $pharmacy->pharmacy_name;
        $pharmacy->delete();
        $this->logActivity('deleted', 'Pharmacy', null, "Deleted pharmacy {$name}");
        return redirect()->route('admin.pharmacies')->with('success', 'Pharmacy deleted successfully.');
    }

    public function approvePharmacy(Pharmacy $pharmacy)
    {
        $pharmacy->update(['status' => 'approved']);
        if ($pharmacy->user) {
            $pharmacy->user->notify(new PharmacyStatusNotification($pharmacy, 'approved'));
        }
        $this->logActivity('approved', 'Pharmacy', $pharmacy->id, "Approved pharmacy {$pharmacy->pharmacy_name}");
        return redirect()->route('admin.pharmacies')->with('success', 'Pharmacy approved successfully.');
    }

    // ── Medicines ─────────────────────────────────────────────────────────────
    public function medicinesPage(Request $request)
    {
        $query = Medicine::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('medicine_name', 'like', "%{$term}%")
                  ->orWhere('manufacturer', 'like', "%{$term}%")
                  ->orWhere('category', 'like', "%{$term}%");
            });
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $medicines = $query->orderBy('medicine_name')->paginate(10)->withQueryString();

        $categories = Medicine::distinct()->pluck('category')->filter()->values();

        return view('admin.medicines', compact('medicines', 'categories'));
    }

    public function addMedicine(): View
    {
        return view('admin.add-medicine');
    }

public function editMedicine(Medicine $medicine): View
    {
        return view('admin.edit-medicine', compact('medicine'));
    }

    public function storeMedicine(Request $request)
    {
        $request->validate([
            'medicine_name'       => 'required|string|max:255',
            'dosage'              => 'nullable|string|max:100',
            'manufacturer'        => 'nullable|string|max:255',
            'category'            => 'nullable|string|max:100',
            'requiresPrescription'=> 'boolean',
        ]);

        $medicine = Medicine::create([
            'medicine_name'        => $request->medicine_name,
            'dosage'               => $request->dosage,
            'manufacturer'         => $request->manufacturer,
            'category'             => $request->category,
            'requiresPrescription' => $request->boolean('requiresPrescription'),
        ]);

        $this->logActivity('created', 'Medicine', $medicine->id, "Created medicine {$medicine->medicine_name}");
        return redirect()->route('admin.medicines')->with('success', 'Medicine added successfully.');
    }

    public function updateMedicine(Request $request, Medicine $medicine)
    {
        $request->validate([
            'medicine_name'       => 'required|string|max:255',
            'dosage'              => 'nullable|string|max:100',
            'manufacturer'        => 'nullable|string|max:255',
            'category'            => 'nullable|string|max:100',
            'requiresPrescription'=> 'boolean',
        ]);

        $medicine->update([
            'medicine_name'        => $request->medicine_name,
            'dosage'               => $request->dosage,
            'manufacturer'         => $request->manufacturer,
            'category'             => $request->category,
            'requiresPrescription' => $request->boolean('requiresPrescription'),
        ]);

        $this->logActivity('updated', 'Medicine', $medicine->id, "Updated medicine {$medicine->medicine_name}");
        return redirect()->route('admin.medicines')->with('success', 'Medicine updated successfully.');
    }

    public function destroyMedicine(Medicine $medicine)
    {
        $name = $medicine->medicine_name;
        $medicine->delete();
        $this->logActivity('deleted', 'Medicine', null, "Deleted medicine {$name}");
        return redirect()->route('admin.medicines')->with('success', 'Medicine deleted successfully.');
    }

    // ── Activity Log Viewer ───────────────────────────────────────────────────
    public function activity(Request $request): View
    {
        $query = ActivityLog::with('user');

        if ($request->filled('action') && $request->action !== 'all') {
            $query->where('action', $request->action);
        }

        if ($request->filled('entity') && $request->entity !== 'all') {
            $query->where('entity_type', $request->entity);
        }

        $activities = $query->latest()->paginate(15)->withQueryString();

        return view('admin.activity', compact('activities'));
    }
}
