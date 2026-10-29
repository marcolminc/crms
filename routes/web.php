<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FineController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RentalController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AzampayWebhookController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboard;
use App\Http\Controllers\Customer\NotificationController;
use App\Http\Controllers\Customer\PaymentController as CustomerPayment;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\ReservationController as CustomerReservation;
use App\Http\Controllers\Customer\RentalController as CustomerRental;
use App\Http\Controllers\VehicleBrowseController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VehicleBrowseController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store']);

    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);

    // FR1: self-service password recovery. POST sides are throttled so the
    // mailer and the token table cannot be spammed.
    Route::get('forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'createReset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1')->name('password.update');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|----------------------------------------------------------------------
| Customer area
|----------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'role:customer'])->group(function () {
    Route::get('dashboard', [CustomerDashboard::class, 'index'])->name('customer.dashboard');

    Route::get('reservations', [CustomerReservation::class, 'index'])->name('customer.reservations.index');
    Route::get('reservations/create', [CustomerReservation::class, 'create'])->name('customer.reservations.create');
    Route::post('reservations', [CustomerReservation::class, 'store'])->name('customer.reservations.store');
    Route::get('reservations/{reservation}', [CustomerReservation::class, 'show'])->name('customer.reservations.show');
    Route::put('reservations/{reservation}', [CustomerReservation::class, 'update'])->name('customer.reservations.update');
    Route::put('reservations/{reservation}/cancel', [CustomerReservation::class, 'cancel'])->name('customer.reservations.cancel');

    Route::get('rentals', [CustomerReservation::class, 'rentals'])->name('customer.rentals.index');
    Route::get('rentals/{rental}', [CustomerRental::class, 'show'])->name('customer.rentals.show');
    Route::put('rentals/{rental}/extend', [CustomerRental::class, 'extend'])->name('customer.rentals.extend');
    Route::post('rentals/{rental}/rate', [CustomerRental::class, 'rate'])->name('customer.rentals.rate');
    Route::post('rentals/{rental}/pay', [CustomerPayment::class, 'store'])->name('customer.rentals.pay');
    Route::get('payments/{payment_intent}/waiting', [CustomerPayment::class, 'waiting'])->name('customer.payments.waiting');
    Route::get('payments/{payment_intent}/status', [CustomerPayment::class, 'status'])->name('customer.payments.status');
    Route::post('payments/{payment_intent}/confirm', [CustomerPayment::class, 'confirm'])->name('customer.payments.confirm');
    Route::get('payments/{payment_intent}/return', [CustomerPayment::class, 'complete'])->name('customer.payments.return');
});

/*
|----------------------------------------------------------------------
| Payment provider callbacks (no session, authenticated by payload)
|----------------------------------------------------------------------
*/
Route::post('webhooks/azampay', AzampayWebhookController::class)->name('webhooks.azampay');

/*
|----------------------------------------------------------------------
| Every signed-in user (customers and staff)
|----------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('profile.password.update');

    // UC12: every actor (customers, staff and drivers) receives notifications.
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

/*
|----------------------------------------------------------------------
| Public vehicle catalogue (browsing is open, booking requires a login)
|----------------------------------------------------------------------
*/
Route::get('vehicles', [VehicleBrowseController::class, 'index'])->name('vehicles.index');
Route::get('vehicles/{vehicle}', [VehicleBrowseController::class, 'show'])->name('vehicles.show');

/*
|----------------------------------------------------------------------
| Staff area
|----------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('vehicles', VehicleController::class)->except(['show']);
    Route::put('vehicles/{vehicle}/status', [VehicleController::class, 'updateStatus'])->name('vehicles.status');

    Route::resource('maintenances', MaintenanceController::class)->except(['show']);

    Route::resource('customers', CustomerController::class);
    Route::get('customers/{customer}/history', [CustomerController::class, 'history'])->name('customers.history');

    Route::resource('reservations', ReservationController::class)->only(['index', 'show', 'destroy']);
    Route::put('reservations/{reservation}/confirm', [ReservationController::class, 'confirm'])->name('reservations.confirm');
    Route::put('reservations/{reservation}/reject', [ReservationController::class, 'reject'])->name('reservations.reject');
    Route::put('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

    Route::resource('rentals', RentalController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::put('rentals/{rental}/cancel', [RentalController::class, 'cancel'])->name('rentals.cancel');

    Route::get('rentals/{rental}/return', [ReturnController::class, 'create'])->name('returns.create');
    Route::post('rentals/{rental}/return', [ReturnController::class, 'store'])->name('returns.store');

    // UC25: traffic fines and liability. The controller limits handling to
    // agents, managers and finance.
    Route::get('fines', [FineController::class, 'index'])->name('fines.index');
    Route::get('rentals/{rental}/fines', [FineController::class, 'create'])->name('fines.create');
    Route::post('rentals/{rental}/fines', [FineController::class, 'store'])->name('fines.store');
    Route::put('fines/{fine}/bill', [FineController::class, 'bill'])->name('fines.bill');
    Route::put('fines/{fine}/absorb', [FineController::class, 'absorb'])->name('fines.absorb');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('rentals/{rental}/payment', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('rentals/{rental}/payment', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');

    // UC14: manager review of material discounts and damage charges. The
    // controller restricts this queue to branch managers and administrators.
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::put('approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::put('approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

    // System administrator only.
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::put('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::put('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');

        // FR14: the audit trail is administrator-only and read-only.
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
    });
});
