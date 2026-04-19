<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\RequireAuth;
use Illuminate\Support\Facades\Route;

// amazonq-ignore-next-line
Route::get('/', fn() => redirect()->route('login'));

// Auth
// amazonq-ignore-next-line
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
// amazonq-ignore-next-line
Route::post('/login', [AuthController::class, 'lookupRealm'])->name('login.submit');
// amazonq-ignore-next-line
Route::get('/login/contact', fn() => view('auth.contact'))->name('login.contact');
// amazonq-ignore-next-line
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
// amazonq-ignore-next-line
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
// amazonq-ignore-next-line
Route::get('/session-check', [AuthController::class, 'sessionCheck'])->name('session.check')->middleware(RequireAuth::class);

// Protected
Route::middleware(RequireAuth::class)->group(function () {
    // amazonq-ignore-next-line
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // amazonq-ignore-next-line
    Route::get('/users', [UserController::class, 'index'])->name('users');
    // amazonq-ignore-next-line
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    // amazonq-ignore-next-line
    Route::put('/users/{userId}', [UserController::class, 'update'])->name('users.update');
    // amazonq-ignore-next-line
    Route::post('/users/{userId}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    // amazonq-ignore-next-line
    Route::post('/users/{userId}/toggle-mailbox', [UserController::class, 'toggleMailbox'])->name('users.toggle-mailbox');
    // amazonq-ignore-next-line
    Route::delete('/users/{userId}', [UserController::class, 'destroy'])->name('users.destroy');
});
