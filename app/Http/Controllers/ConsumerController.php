<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\Http\Request;

class ConsumerController extends Controller
{
    public function index()
    {
        $pharmacies = Pharmacy::where('status', 'approved')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
            
        return view('consumer.dashboard', compact('pharmacies'));
    }

    public function pharmacyDetails($id)
    {
        $pharmacy = Pharmacy::with('inventory.medicine')
            ->where('status', 'approved')
            ->findOrFail($id);
            
        return view('consumer.pharmacy-details', compact('pharmacy'));
    }
}