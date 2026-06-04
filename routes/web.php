<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MedicineSearchController;

// Public route (Consumer)
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/api/search', [MedicineSearchController::class, 'search']);

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::get('/profile', function () {
    return view('profile.edit');
})->middleware(['auth'])->name('profile.edit');

// Authentication routes (provided by Breeze)
require __DIR__.'/auth.php';

// Pharmacy Operator Dashboard (requires login and role)
Route::middleware(['auth', 'role:pharmacy_operator'])->prefix('pharmacy')->group(function () {
    Route::get('/dashboard', function () {
        return view('pharmacy.dashboard');
    })->name('pharmacy.dashboard');
    
    Route::get('/api/inventory', [App\Http\Controllers\PharmacyDashboardController::class, 'getInventory']);
    Route::post('/api/update-stock', [App\Http\Controllers\PharmacyDashboardController::class, 'updateStock']);
});

// Admin Dashboard (requires login and admin role)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');
    
    Route::get('/api/users', [App\Http\Controllers\AdminDashboardController::class, 'getUsers']);
    Route::get('/api/pharmacies', [App\Http\Controllers\AdminDashboardController::class, 'getPharmacies']);
    Route::get('/api/logs', [App\Http\Controllers\AdminDashboardController::class, 'getLogs']);
});