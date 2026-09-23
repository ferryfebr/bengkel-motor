<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\MechanicController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// Queue Board lama dihapus - lihat route work-orders.queue di dalam grup auth.

Route::middleware(['auth', 'track.impersonation'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Impersonation ("Login Sebagai") - owner & super_admin.
    Route::middleware('role:owner,super_admin')->group(function () {
        Route::get('impersonation', [ImpersonationController::class, 'index'])->name('impersonation.index');
        Route::post('impersonation', [ImpersonationController::class, 'store'])->name('impersonation.store');
    });

    // Stop impersonation - boleh diakses siapa pun yang sedang di-impersonate.
    Route::delete('impersonation', [ImpersonationController::class, 'destroy'])->name('impersonation.stop');

    // Work Order (antrean servis paralel) - kasir & owner/super_admin.
    Route::middleware('role:kasir,owner,super_admin')->group(function () {
        Route::get('work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
        Route::get('work-orders/queue', [WorkOrderController::class, 'queue'])->name('work-orders.queue');
        Route::get('work-orders/completed', [WorkOrderController::class, 'completed'])->name('work-orders.completed');
        Route::get('work-orders/completed/export', [WorkOrderController::class, 'exportCompleted'])->name('work-orders.completed.export');
        Route::get('work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
        Route::post('work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
        Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
        Route::patch('work-orders/{workOrder}/status', [WorkOrderController::class, 'updateStatus'])->name('work-orders.status');
    });

    // POS - checkout & struk.
    Route::middleware('role:kasir,owner,super_admin')->group(function () {
        Route::get('pos/{transaction}', [PosController::class, 'show'])->name('pos.show');
        Route::post('pos/{transaction}/draft', [PosController::class, 'saveDraft'])->name('pos.draft');
        Route::post('pos/{transaction}/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::get('pos/{transaction}/receipt', [PosController::class, 'receipt'])->name('pos.receipt');
    });

    // Kas Bengkel - kasir boleh input mutasi; owner/super_admin lihat semua.
    Route::middleware('role:kasir,owner,super_admin')->group(function () {
        Route::get('cash', [CashController::class, 'index'])->name('cash.index');
        Route::post('cash', [CashController::class, 'store'])->name('cash.store');
    });

    // Penarikan kas - hanya owner & super_admin.
    Route::middleware('role:owner,super_admin')->group(function () {
        Route::post('cash/withdraw', [CashController::class, 'withdraw'])->name('cash.withdraw');
    });

    // Produk: semua role login bisa kelola (kasir update stok & harga jual, tanpa HPP).
    Route::prefix('manage')->name('manage.')->group(function () {
        Route::get('/', fn () => redirect()->route('manage.products.index'))->name('index');

        Route::get('products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');
        Route::resource('products', ProductController::class)->except(['show']);
    });

    // Zona khusus Owner & Super Admin
    Route::middleware('role:owner,super_admin')->prefix('manage')->name('manage.')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('mechanics', MechanicController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
    });

    // Laporan - omset kotor & komisi mekanik untuk semua role; omset bersih owner/admin.
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('gross', [ReportController::class, 'gross'])->name('gross');
        Route::get('mechanics', [ReportController::class, 'mechanics'])->name('mechanics');
        Route::get('export-transactions', [ReportController::class, 'exportTransactions'])->name('export-transactions');

        Route::middleware('role:owner,super_admin')->group(function () {
            Route::get('net', [ReportController::class, 'net'])->name('net');
            Route::post('rebuild-summaries', [ReportController::class, 'rebuildSummaries'])->name('rebuild-summaries');
        });
    });

    // Aktivitas - hanya owner & super_admin.
    Route::middleware('role:owner,super_admin')->prefix('activity')->name('activity.')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->name('index');
        Route::get('transaction/{transaction}', [ActivityController::class, 'show'])->name('show');
    });

    // Zona khusus Super Admin
    Route::middleware('role:super_admin')->prefix('system')->name('system.')->group(function () {
        Route::get('/', [SystemController::class, 'index'])->name('index');
        Route::get('activity-logs/export', [SystemController::class, 'exportActivityLogs'])->name('activity-logs.export');
    });
});

require __DIR__.'/auth.php';

