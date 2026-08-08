<?php
// routes/web.php

use App\Http\Controllers\ConsumerController;
use App\Http\Controllers\PharmacyDashboardController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\MedicineSearchController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

// ============================================
// PUBLIC ROUTES
// ============================================
Route::get('/', [ConsumerController::class, 'index'])->name('home');

// ============================================
// DASHBOARD ROUTE (Redirects based on role)
// ============================================
Route::get('/dashboard', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    $role = auth()->user()->role;

    if ($role === 'admin') {
        return redirect()->route('admin.dashboard');
    } elseif ($role === 'pharmacy') {
        return redirect()->route('pharmacy.dashboard');
    } else {
        return redirect()->route('consumer.dashboard');
    }
})->middleware(['auth'])->name('dashboard');

// ============================================
// AUTH ROUTES (Laravel Breeze)
// ============================================
require __DIR__.'/auth.php';

// ============================================
// CONSUMER ROUTES
// ============================================
Route::middleware(['auth', 'role:consumer'])->prefix('consumer')->name('consumer.')->group(function () {
    Route::get('/dashboard', [ConsumerController::class, 'index'])->name('dashboard');
    Route::get('/search', [ConsumerController::class, 'search'])->name('search');
    Route::get('/pharmacy/{id}', [ConsumerController::class, 'pharmacyDetails'])->name('pharmacy.details');
    Route::post('/message/send', [MessageController::class, 'store'])->name('message.send');
    Route::get('/messages', [MessageController::class, 'consumerConversations'])->name('messages');
});

// ============================================
// PHARMACY ROUTES
// ============================================
Route::middleware(['auth', 'role:pharmacy,pharmacy_operator'])->prefix('pharmacy')->name('pharmacy.')->group(function () {
    Route::get('/dashboard', [PharmacyDashboardController::class, 'index'])->name('dashboard');
    Route::get('/inventory', [PharmacyDashboardController::class, 'inventory'])->name('inventory');
    Route::post('/inventory/update', [PharmacyDashboardController::class, 'updateInventory'])->name('inventory.update');
    Route::get('/messages', [PharmacyDashboardController::class, 'messages'])->name('messages');
    Route::post('/message/reply/{id}', [PharmacyDashboardController::class, 'replyMessage'])->name('message.reply');
    Route::get('/message/mark-read/{id}', [PharmacyDashboardController::class, 'markRead'])->name('message.mark-read');

    // AJAX endpoints for pharmacy messaging (return JSON) - used by frontend polling and mark-as-read buttons
    Route::get('/unread-count', [MessageController::class, 'unreadCount'])->name('unread.count');
    Route::post('/message/mark-read-ajax/{id}', [MessageController::class, 'markReadAjax'])->name('message.mark-read-ajax');
    Route::post('/message/mark-unread-ajax/{id}', [MessageController::class, 'markUnreadAjax'])->name('message.mark-unread-ajax');
    Route::post('/message/verify-ajax/{id}', [MessageController::class, 'verifyAjax'])->name('message.verify-ajax');
});

// ============================================
// ADMIN ROUTES
// ============================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users');
    Route::delete('/user/{id}', [AdminDashboardController::class, 'deleteUser'])->name('user.delete');
    Route::get('/pharmacies', [AdminDashboardController::class, 'pharmacies'])->name('pharmacies');
    Route::get('/pharmacy/add', [AdminDashboardController::class, 'addPharmacy'])->name('pharmacy.add');
    Route::post('/pharmacy/store', [AdminDashboardController::class, 'storePharmacy'])->name('pharmacy.store');
    Route::delete('/pharmacy/{id}', [AdminDashboardController::class, 'deletePharmacy'])->name('pharmacy.delete');
    Route::get('/logs', [AdminDashboardController::class, 'logs'])->name('logs');
});

