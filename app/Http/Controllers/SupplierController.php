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

    public function edit($id)
    {
        $supplier = Supplier::findOrFail($id);
        return view('pharmacy.suppliers_edit', compact('supplier'));
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
        ]);

        $supplier->update($data);
        return redirect()->route('pharmacy.suppliers.index')->with('success', 'Supplier updated');
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();
        return redirect()->route('pharmacy.suppliers.index')->with('success', 'Supplier deleted');
    }
}
