<?php

use App\Http\Controllers\Web\Admin\ExtensionController;
use App\Http\Controllers\Web\Admin\GarageController;
use App\Http\Controllers\Web\Admin\PaymentVerificationController;
use App\Http\Controllers\Web\Admin\RenterVerificationController;
use App\Http\Controllers\Web\Admin\ReportController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ChargingController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\RentalController;
use App\Http\Controllers\Web\VehicleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Renters approved by an admin
    Route::middleware(['role:renter', 'approved'])->group(function () {
        Route::resource('vehicles', VehicleController::class)->only(['index', 'create', 'store']);

        Route::get('rentals', [RentalController::class, 'index'])->name('rentals.index');
        Route::get('rentals/create', [RentalController::class, 'create'])->name('rentals.create');
        Route::post('rentals', [RentalController::class, 'store'])->name('rentals.store');
        Route::get('rentals/{rental}', [RentalController::class, 'show'])->name('rentals.show');

        Route::get('rentals/{rental}/chargings/create', [ChargingController::class, 'create'])->name('chargings.create');
        Route::post('rentals/{rental}/chargings', [ChargingController::class, 'store'])->name('chargings.store');
    });

    // Admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('renters', [RenterVerificationController::class, 'index'])->name('renters.index');
        Route::get('renters/{user}/ktp', [RenterVerificationController::class, 'showKtp'])->name('renters.ktp');
        Route::patch('renters/{user}/approve', [RenterVerificationController::class, 'approve'])->name('renters.approve');
        Route::patch('renters/{user}/reject', [RenterVerificationController::class, 'reject'])->name('renters.reject');

        // Garages can only be created and viewed, never edited or deleted.
        Route::resource('garages', GarageController::class)->only(['index', 'create', 'store']);

        Route::get('extensions', [ExtensionController::class, 'index'])->name('extensions.index');
        Route::get('extensions/{rental}', [ExtensionController::class, 'create'])->name('extensions.create');
        Route::post('extensions/{rental}', [ExtensionController::class, 'store'])->name('extensions.store');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        Route::get('payments', [PaymentVerificationController::class, 'index'])->name('payments.index');
        Route::patch('payments/rentals/{rental}/verify', [PaymentVerificationController::class, 'verifyRental'])->name('payments.rentals.verify');
        Route::patch('payments/rentals/{rental}/reject', [PaymentVerificationController::class, 'rejectRental'])->name('payments.rentals.reject');
        Route::patch('payments/chargings/{charging}/verify', [PaymentVerificationController::class, 'verifyCharging'])->name('payments.chargings.verify');
        Route::patch('payments/chargings/{charging}/reject', [PaymentVerificationController::class, 'rejectCharging'])->name('payments.chargings.reject');
    });
});
