<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureSuperAdmin::class])->group(function () {
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    // Registered after /accounts/create so the literal segment wins.
    Route::get('/accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');

    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users/{user}/impersonate', [ImpersonationController::class, 'start'])
        ->name('admin.impersonate.start');
});

// Not gated by EnsureSuperAdmin — while impersonating, the authenticated
// user is the impersonated (possibly non-admin) user, not the admin.
Route::middleware(['auth'])
    ->post('/admin/impersonate/stop', [ImpersonationController::class, 'stop'])
    ->name('admin.impersonate.stop');
