<?php
// routes/web.php

use App\Http\Controllers\ConsumerController;
use App\Http\Controllers\PharmacyDashboardController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AdminInventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SurveyController;
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
// PHARMACY PENDING APPROVAL PAGE
// ============================================
Route::get('/pharmacy/pending', function () {
    return view('auth.pharmacy-pending');
})->middleware('auth')->name('consumer.pharmacy.pending');

// ============================================
// SURVEY ROUTES (ISO/IEC 25010 Evaluation)
// ============================================
// Public form — anyone can fill it out
Route::get('/survey', [SurveyController::class, 'show'])->name('survey.show');
Route::post('/survey', [SurveyController::class, 'store'])->name('survey.store');
Route::get('/survey/thankyou', [SurveyController::class, 'thankyou'])->name('survey.thankyou');

// ============================================
// NOTIFICATION ROUTES (all authenticated users)
// ============================================
Route::middleware(['auth'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
    Route::post('/{id}/mark-read', [NotificationController::class, 'markRead'])->name('mark-read');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
});

// ============================================
// CONSUMER ROUTES
// ============================================
Route::middleware(['auth', 'role:consumer'])->prefix('consumer')->name('consumer.')->group(function () {
    Route::get('/dashboard', [ConsumerController::class, 'index'])->name('dashboard');
    Route::get('/search', [ConsumerController::class, 'search'])->name('search');
    Route::get('/pharmacy/{id}', [ConsumerController::class, 'pharmacyDetails'])->name('pharmacy.details');
    Route::post('/message/send', [MessageController::class, 'store'])->name('message.send');
    Route::get('/messages', [MessageController::class, 'consumerConversations'])->name('messages');
    Route::delete('/messages/{pharmacyId}', [MessageController::class, 'deleteConversation'])->name('messages.delete');
    Route::get('/messages/data', [MessageController::class, 'consumerMessagesJson'])->name('messages.json');
    Route::get('/profile', function() { return view('consumer.profile'); })->name('profile.settings');
});

// ============================================
// PHARMACY ROUTES
// ============================================
Route::middleware(['auth', 'role:pharmacy,pharmacy_operator', 'pharmacy.pending'])->prefix('pharmacy')->name('pharmacy.')->group(function () {
    Route::get('/dashboard', [PharmacyDashboardController::class, 'index'])->name('dashboard');
    // Inventory management (CRUD, search, pagination)
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{id}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{id}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    // Legacy bulk update endpoint kept for compatibility
    Route::post('/inventory/update', [PharmacyDashboardController::class, 'updateInventory'])->name('inventory.bulk-update');

    // Receiving & suppliers
    Route::get('/receiving/create', [\App\Http\Controllers\ReceivingController::class, 'create'])->name('receiving.create');
    Route::post('/receiving', [\App\Http\Controllers\ReceivingController::class, 'store'])->name('receiving.store');

    Route::get('/suppliers', [\App\Http\Controllers\SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [\App\Http\Controllers\SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [\App\Http\Controllers\SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{id}/edit', [\App\Http\Controllers\SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{id}', [\App\Http\Controllers\SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{id}', [\App\Http\Controllers\SupplierController::class, 'destroy'])->name('suppliers.destroy');
    Route::get('/messages', [PharmacyDashboardController::class, 'messages'])->name('messages');
    Route::post('/message/reply/{id}', [PharmacyDashboardController::class, 'replyMessage'])->name('message.reply');
    Route::get('/message/mark-read/{id}', [PharmacyDashboardController::class, 'markRead'])->name('message.mark-read');

    // AJAX endpoints for pharmacy messaging (return JSON) - used by frontend polling and mark-as-read buttons
    Route::get('/unread-count', [MessageController::class, 'unreadCount'])->name('unread.count');
    Route::post('/message/mark-read-ajax/{id}', [MessageController::class, 'markReadAjax'])->name('message.mark-read-ajax');
    Route::post('/message/mark-unread-ajax/{id}', [MessageController::class, 'markUnreadAjax'])->name('message.mark-unread-ajax');
    Route::post('/message/verify-ajax/{id}', [MessageController::class, 'verifyAjax'])->name('message.verify-ajax');
    // Secure prescription image viewer (decrypts on-the-fly, never serves raw public URL)
    Route::get('/prescription/{message}', [MessageController::class, 'servePrescription'])->name('prescription.serve');

    // Inventory Analysis (ABC/VED)
    Route::get('/analysis', [\App\Http\Controllers\AnalysisController::class, 'index'])->name('analysis');

    // Cycle counts
    Route::get('/cycle-counts', [\App\Http\Controllers\CycleCountController::class, 'index'])->name('cycle-counts.index');
    Route::get('/cycle-counts/create', [\App\Http\Controllers\CycleCountController::class, 'create'])->name('cycle-counts.create');
    Route::post('/cycle-counts', [\App\Http\Controllers\CycleCountController::class, 'store'])->name('cycle-counts.store');
    Route::get('/cycle-counts/{id}/count', [\App\Http\Controllers\CycleCountController::class, 'show'])->name('cycle-counts.show');
    Route::post('/cycle-counts/{id}/complete', [\App\Http\Controllers\CycleCountController::class, 'complete'])->name('cycle-counts.complete');

    // Returns & recalls
    Route::get('/returns', [\App\Http\Controllers\ReturnRecallController::class, 'index'])->name('returns.index');
    Route::get('/returns/create', [\App\Http\Controllers\ReturnRecallController::class, 'create'])->name('returns.create');
    Route::post('/returns', [\App\Http\Controllers\ReturnRecallController::class, 'store'])->name('returns.store');
    Route::post('/returns/{id}/status', [\App\Http\Controllers\ReturnRecallController::class, 'updateStatus'])->name('returns.status');

    // Controlled substance logbook
    Route::get('/controlled-substances', [\App\Http\Controllers\ControlledSubstanceController::class, 'index'])->name('controlled-substances.index');
    Route::get('/controlled-substances/log', [\App\Http\Controllers\ControlledSubstanceController::class, 'create'])->name('controlled-substances.create');
    Route::post('/controlled-substances/log', [\App\Http\Controllers\ControlledSubstanceController::class, 'store'])->name('controlled-substances.store');

    // Inventory audit log
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log');

    // Pharmacy profile
    Route::get('/profile', [\App\Http\Controllers\PharmacyProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\PharmacyProfileController::class, 'update'])->name('profile.update');

    // Requirements upload
    Route::get('/requirements', [\App\Http\Controllers\PharmacyRequirementsController::class, 'show'])->name('requirements');
    Route::post('/requirements', [\App\Http\Controllers\PharmacyRequirementsController::class, 'store'])->name('requirements.store');

    // Inventory CSV export
    Route::get('/inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
});

// ============================================
// ADMIN ROUTES
// ============================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

// Users
    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users');
    Route::get('/user/{user}/edit', [AdminDashboardController::class, 'editUser'])->name('user.edit');
    Route::put('/user/{user}', [AdminDashboardController::class, 'updateUser'])->name('user.update');
    Route::delete('/user/{user}', [AdminDashboardController::class, 'deleteUser'])->name('user.delete');

    // Pharmacies
    Route::get('/pharmacies', [AdminDashboardController::class, 'pharmacies'])->name('pharmacies');
    Route::get('/pharmacy/add', [AdminDashboardController::class, 'addPharmacy'])->name('pharmacy.add');
    Route::post('/pharmacy/store', [AdminDashboardController::class, 'storePharmacy'])->name('pharmacy.store');
    Route::get('/pharmacy/{pharmacy}/edit', [AdminDashboardController::class, 'editPharmacy'])->name('pharmacy.edit');
    Route::put('/pharmacy/{pharmacy}', [AdminDashboardController::class, 'updatePharmacy'])->name('pharmacy.update');
    Route::post('/pharmacy/{pharmacy}/approve', [AdminDashboardController::class, 'approvePharmacy'])->name('pharmacy.approve');
    Route::delete('/pharmacy/{pharmacy}', [AdminDashboardController::class, 'deletePharmacy'])->name('pharmacy.delete');

    // Medicines
    Route::get('/medicines', [AdminDashboardController::class, 'medicinesPage'])->name('medicines');
    Route::get('/medicine/add', [AdminDashboardController::class, 'addMedicine'])->name('medicine.add');
    Route::post('/medicine/store', [AdminDashboardController::class, 'storeMedicine'])->name('medicine.store');
    Route::get('/medicine/{medicine}/edit', [AdminDashboardController::class, 'editMedicine'])->name('medicine.edit');
    Route::put('/medicine/{medicine}', [AdminDashboardController::class, 'updateMedicine'])->name('medicine.update');
    Route::delete('/medicine/{medicine}', [AdminDashboardController::class, 'destroyMedicine'])->name('medicine.delete');

// Logs
    Route::get('/logs', [AdminDashboardController::class, 'logs'])->name('logs');

    // Activity Log
    Route::get('/activity', [AdminDashboardController::class, 'activity'])->name('activity');

    // Inventory overview
    Route::get('/inventory', [AdminInventoryController::class, 'index'])->name('inventory');

    // Survey results
    Route::get('/survey/results', [SurveyController::class, 'results'])->name('survey.results');

    // Requirements review
    Route::get('/requirements', [AdminDashboardController::class, 'requirements'])->name('requirements');
    Route::post('/pharmacy/{pharmacy}/requirements/approve', [AdminDashboardController::class, 'approveRequirements'])->name('requirements.approve');
    Route::post('/pharmacy/{pharmacy}/requirements/reject', [AdminDashboardController::class, 'rejectRequirements'])->name('requirements.reject');
    // Serve a private requirement file securely
    Route::get('/pharmacy/{pharmacy}/requirement/{key}', [AdminDashboardController::class, 'serveRequirement'])->name('requirement.file');
});

