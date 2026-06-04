<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin']);
    }
    
    public function getUsers()
    {
        $users = User::all();
        return response()->json($users);
    }
    
    public function getPharmacies()
    {
        // Demo data
        $pharmacies = [
            ['id' => 1, 'name' => 'Mercury Drug', 'address' => 'Rizal St.', 'contact' => '123-4567', 'status' => 'active'],
            ['id' => 2, 'name' => 'Watsons', 'address' => 'Pacific Mall', 'contact' => '123-4568', 'status' => 'pending'],
        ];
        return response()->json($pharmacies);
    }
    
    public function getLogs()
    {
        // Demo logs
        $logs = [
            ['time' => now(), 'action' => 'User logged in', 'type' => 'auth'],
            ['time' => now(), 'action' => 'Medicine searched', 'type' => 'search'],
        ];
        return response()->json($logs);
    }
}