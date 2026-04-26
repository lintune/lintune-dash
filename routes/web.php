<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\RequireAuth;
use Illuminate\Support\Facades\Route;


Route::get('/', fn() => redirect()->route('login'));

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'lookupRealm'])->name('login.submit');
Route::get('/login/contact', fn() => view('auth.contact'))->name('login.contact');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/session-check', [AuthController::class, 'sessionCheck'])->name('session.check')->middleware(RequireAuth::class);

// Protected
Route::middleware(RequireAuth::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{userId}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{userId}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::post('/users/{userId}/toggle-mailbox', [UserController::class, 'toggleMailbox'])->name('users.toggle-mailbox');
    Route::post('/users/{userId}/toggle-nextcloud', [UserController::class, 'toggleNextcloud'])->name('users.toggle-nextcloud');
    Route::delete('/users/{userId}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs');
});
