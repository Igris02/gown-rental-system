<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\GownController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\WalkInController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ReservationController;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Support\Facades\Route;

// The catalog is the public front page
Route::get('/', fn () => redirect()->route('catalog.index'));

// Public pages: anyone can browse without an account
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{gown}', [CatalogController::class, 'show'])
    ->whereNumber('gown')
    ->name('catalog.show');

// Only for visitors who are not signed in
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.submit');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

// Only for signed-in users
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Customer reservations
    Route::get('/my-reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/catalog/{gown}/reserve', [ReservationController::class, 'store'])
        ->whereNumber('gown')
        ->middleware('throttle:20,1')
        ->name('reservations.store');
    Route::post('/my-reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])
        ->whereNumber('reservation')
        ->name('reservations.cancel');
});

// Admin and staff pages
Route::middleware(['auth', EnsureUserHasRole::class . ':admin,staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('gowns', GownController::class)->except(['show', 'destroy']);

        // Only admins can delete gowns
        Route::delete('gowns/{gown}', [GownController::class, 'destroy'])
            ->middleware(EnsureUserHasRole::class . ':admin')
            ->name('gowns.destroy');

        // Reservations: admin and staff can both review and update them
        Route::get('reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::post('reservations/{reservation}/status', [AdminReservationController::class, 'updateStatus'])
            ->whereNumber('reservation')
            ->name('reservations.status');

        // Walk-in bookings: staff reserve a gown at the shop for a customer
        Route::get('walk-ins/create', [WalkInController::class, 'create'])->name('walk-ins.create');
        Route::post('walk-ins', [WalkInController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('walk-ins.store');

        // Audit log and reports: admins only
        Route::middleware(EnsureUserHasRole::class . ':admin')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [ReportController::class, 'export'])
                ->middleware('throttle:10,1')
                ->name('reports.export');

            Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
            Route::get('audit-log/export', [AuditLogController::class, 'export'])
                ->middleware('throttle:10,1')
                ->name('audit-log.export');
        });
    });
