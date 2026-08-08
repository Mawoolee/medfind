<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderBy('name')->paginate(20);
        return view('pharmacy.suppliers_index', compact('suppliers'));
    }

    public function create()
    {
        return view('pharmacy.suppliers_create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
        ]);

        Supplier::create($data);
        return redirect()->route('pharmacy.suppliers.index')->with('success', 'Supplier added');
    }
}
