<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MedicineSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('query', '');
        
        // Sample data for MedFind
        $pharmacies = [
            [
                'id' => 1,
                'name' => 'Mercury Drug - Legazpi',
                'address' => 'Rizal St., Legazpi City',
                'latitude' => 13.1486,
                'longitude' => 123.7412,
                'medicines' => [
                    ['name' => 'Paracetamol 500mg', 'price' => 85, 'stock' => 45, 'requires_prescription' => false],
                    ['name' => 'Amoxicillin 500mg', 'price' => 120, 'stock' => 23, 'requires_prescription' => true],
                    ['name' => 'Ibuprofen 200mg', 'price' => 95, 'stock' => 12, 'requires_prescription' => false],
                ]
            ],
            [
                'id' => 2,
                'name' => 'Watsons - Pacific Mall',
                'address' => 'Pacific Mall, Legazpi City',
                'latitude' => 13.1500,
                'longitude' => 123.7480,
                'medicines' => [
                    ['name' => 'Paracetamol 500mg', 'price' => 92, 'stock' => 28, 'requires_prescription' => false],
                    ['name' => 'Amoxicillin 500mg', 'price' => 135, 'stock' => 8, 'requires_prescription' => true],
                ]
            ],
            [
                'id' => 3,
                'name' => 'South Star Drug',
                'address' => 'Lapu-Lapu St., Legazpi City',
                'latitude' => 13.1550,
                'longitude' => 123.7350,
                'medicines' => [
                    ['name' => 'Paracetamol 500mg', 'price' => 78, 'stock' => 3, 'requires_prescription' => false],
                    ['name' => 'Ibuprofen 200mg', 'price' => 88, 'stock' => 15, 'requires_prescription' => false],
                ]
            ],
        ];
        
        // Filter by query
        if ($query) {
            $filteredPharmacies = [];
            foreach ($pharmacies as $pharmacy) {
                $matchingMeds = array_filter($pharmacy['medicines'], function($medicine) use ($query) {
                    return stripos($medicine['name'], $query) !== false;
                });
                if (count($matchingMeds) > 0) {
                    $pharmacy['medicines'] = array_values($matchingMeds);
                    $filteredPharmacies[] = $pharmacy;
                }
            }
            $pharmacies = $filteredPharmacies;
        }
        
        return response()->json([
            'success' => true,
            'data' => $pharmacies
        ]);
    }
}