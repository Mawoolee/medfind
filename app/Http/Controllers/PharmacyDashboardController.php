<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PharmacyDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:pharmacy_operator']);
    }
    
    public function getInventory()
    {
        // Demo data
        $inventory = [
            ['id' => 1, 'medicine_name' => 'Paracetamol', 'dosage' => '500mg', 'stock' => 45, 'price' => 85, 'status' => 'in_stock'],
            ['id' => 2, 'medicine_name' => 'Amoxicillin', 'dosage' => '500mg', 'stock' => 23, 'price' => 120, 'status' => 'in_stock'],
            ['id' => 3, 'medicine_name' => 'Ibuprofen', 'dosage' => '200mg', 'stock' => 8, 'price' => 95, 'status' => 'low_stock'],
        ];
        
        return response()->json($inventory);
    }
    
    public function updateStock(Request $request)
    {
        // Validate and update stock
        return response()->json(['success' => true, 'message' => 'Stock updated']);
    }
}