<?php

use App\Http\Controllers\LaundryController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackingController::class, 'index'])->name('tracking.home');

Route::get('dashboard', [LaundryController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('track/{token}', [TrackingController::class, 'show'])->middleware('throttle:30,1')->name('tracking.show');
Route::post('track', [TrackingController::class, 'lookup'])->middleware('throttle:10,1')->name('tracking.lookup');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('pos', [LaundryController::class, 'pos'])->name('pos');
    Route::post('orders', [LaundryController::class, 'storeOrder'])->name('orders.store');
    Route::get('orders', [LaundryController::class, 'orders'])->name('orders.index');
    Route::get('orders/{order}', [LaundryController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [LaundryController::class, 'updateStatus'])->middleware('role:administrator,manager,laundry_staff')->name('orders.status');
    Route::post('orders/{order}/payments', [LaundryController::class, 'addPayment'])->middleware('role:administrator,manager,cashier')->name('orders.payments.store');
    Route::post('payments/{payment}/reverse', [LaundryController::class, 'reversePayment'])->middleware('role:administrator,manager')->name('payments.reverse');
    Route::patch('payments/{payment}/verify', [LaundryController::class, 'verifyPayment'])->middleware('role:administrator,manager')->name('payments.verify');
    Route::get('reports', [LaundryController::class, 'report'])->middleware('role:administrator,manager')->name('reports');
    Route::get('reports/export', [LaundryController::class, 'exportReport'])->middleware('role:administrator,manager')->name('reports.export');
    Route::post('expenses', [LaundryController::class, 'storeExpense'])->middleware('role:administrator,manager')->name('expenses.store');
    Route::post('shifts', [LaundryController::class, 'openShift'])->middleware('role:administrator,manager,cashier')->name('shifts.open');
    Route::patch('shifts/{shift}/close', [LaundryController::class, 'closeShift'])->middleware('role:administrator,manager,cashier')->name('shifts.close');
    Route::get('shifts', [LaundryController::class, 'shifts'])->middleware('role:administrator,manager,cashier')->name('shifts');
    Route::middleware('role:administrator')->group(function () {
        Route::get('admin/settings', [LaundryController::class, 'settings'])->name('admin.settings');
        Route::put('admin/settings', [LaundryController::class, 'updateSettings'])->name('admin.settings.update');
    });
    Route::middleware('role:administrator')->group(function () {
        Route::get('admin/services', [LaundryController::class, 'services'])->name('admin.services');
        Route::post('admin/services', [LaundryController::class, 'storeService'])->name('admin.services.store');
        Route::put('admin/services/{service}', [LaundryController::class, 'updateService'])->name('admin.services.update');
        Route::patch('admin/services/{service}/toggle', [LaundryController::class, 'toggleService'])->name('admin.services.toggle');
        Route::delete('admin/services/{service}', [LaundryController::class, 'deleteService'])->name('admin.services.delete');
        Route::get('admin/users', [LaundryController::class, 'users'])->name('admin.users');
        Route::post('admin/users', [LaundryController::class, 'storeUser'])->name('admin.users.store');
    });
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
