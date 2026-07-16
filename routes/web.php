<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MedicineSearchController;
use App\Http\Controllers\ConsumerController;
use App\Http\Controllers\PharmacyDashboardController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\MessageController;

// ── Public ──────────────────────────────────────────────────────────────────
Route::get('/', [ConsumerController::class, 'index'])->name('home');
Route::get('/search', [MedicineSearchController::class, 'search'])->name('medicine.search');

// ── Auth ─────────────────────────────────────────────────────────────────────
require __DIR__.'/auth.php';

// Breeze fallback redirect by role
Route::get('/dashboard', function () {
    $role = auth()->user()->role;
    if ($role === 'admin')             return redirect()->route('admin.dashboard');
    if ($role === 'pharmacy_operator') return redirect()->route('pharmacy.dashboard');
    return redirect()->route('home');
})->middleware('auth')->name('dashboard');

// ── Consumer ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:consumer'])->group(function () {
    Route::get('/messages',                        [MessageController::class, 'consumerInbox'])->name('consumer.messages');
    Route::get('/messages/{pharmacy}',             [MessageController::class, 'consumerThread'])->name('consumer.messages.thread');
    Route::post('/messages/{pharmacy}',            [MessageController::class, 'consumerSend'])->name('consumer.messages.send');
});

// ── Pharmacy Operator ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:pharmacy_operator'])->prefix('pharmacy')->name('pharmacy.')->group(function () {
    Route::get('/dashboard',                       [PharmacyDashboardController::class, 'index'])->name('dashboard');

    // Inventory
    Route::post('/inventory',                      [PharmacyDashboardController::class, 'store'])->name('inventory.store');
    Route::put('/inventory/{item}',                [PharmacyDashboardController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{item}',             [PharmacyDashboardController::class, 'destroy'])->name('inventory.destroy');

    // Messages
    Route::get('/messages',                        [MessageController::class, 'pharmacyInbox'])->name('messages');
    Route::get('/messages/{consumer}',             [MessageController::class, 'pharmacyThread'])->name('messages.thread');
    Route::post('/messages/{consumer}',            [MessageController::class, 'pharmacyReply'])->name('messages.reply');
    Route::post('/messages/{message}/read',        [MessageController::class, 'markRead'])->name('messages.read');
});

// ── Admin ─────────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard',                       [AdminDashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::get('/users',                           [AdminDashboardController::class, 'users'])->name('users');
    Route::put('/users/{user}',                    [AdminDashboardController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}',                 [AdminDashboardController::class, 'destroyUser'])->name('users.destroy');

    // Pharmacies
    Route::get('/pharmacies',                      [AdminDashboardController::class, 'pharmacies'])->name('pharmacies');
    Route::post('/pharmacies',                     [AdminDashboardController::class, 'storePharmacy'])->name('pharmacies.store');
    Route::put('/pharmacies/{pharmacy}',           [AdminDashboardController::class, 'updatePharmacy'])->name('pharmacies.update');
    Route::delete('/pharmacies/{pharmacy}',        [AdminDashboardController::class, 'destroyPharmacy'])->name('pharmacies.destroy');
    Route::post('/pharmacies/{pharmacy}/approve',  [AdminDashboardController::class, 'approvePharmacy'])->name('pharmacies.approve');

    // Medicines
    Route::get('/medicines',                       [AdminDashboardController::class, 'medicines'])->name('medicines');
    Route::post('/medicines',                      [AdminDashboardController::class, 'storeMedicine'])->name('medicines.store');
    Route::put('/medicines/{medicine}',            [AdminDashboardController::class, 'updateMedicine'])->name('medicines.update');
    Route::delete('/medicines/{medicine}',         [AdminDashboardController::class, 'destroyMedicine'])->name('medicines.destroy');
});
