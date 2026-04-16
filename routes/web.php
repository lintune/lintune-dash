<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Super\SuperAuthController;
use App\Http\Controllers\Super\SuperRealmController;
use App\Http\Middleware\RequireAuth;
use App\Http\Middleware\RequireSuperAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('login'));

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'lookupRealm'])->name('login.submit');
Route::get('/login/contact', fn() => view('auth.contact'))->name('login.contact');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected
Route::middleware(RequireAuth::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [UserController::class, 'index'])->name('users');
});

// Super admin
Route::prefix('super')->name('super.')->group(function () {
    Route::get('/login', [SuperAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [SuperAuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [SuperAuthController::class, 'logout'])->name('logout');

    Route::middleware(RequireSuperAuth::class)->group(function () {
        Route::get('/realms', [SuperRealmController::class, 'index'])->name('realms');
        Route::get('/realms/create', [SuperRealmController::class, 'create'])->name('realms.create');
        Route::post('/realms', [SuperRealmController::class, 'store'])->name('realms.store');
    });
});
