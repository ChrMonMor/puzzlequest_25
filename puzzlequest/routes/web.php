<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Web auth routes
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login'])->name('login.post');

Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [WebAuthController::class, 'register'])->name('register.post');

Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// Guest creation
Route::post('/guest', [WebAuthController::class, 'createGuest'])->name('guest.create');
Route::get('/guest', [WebAuthController::class, 'showGuest'])->name('guest');

// Upgrade guest to full user (session-based)
Route::get('/upgrade', [WebAuthController::class, 'showUpgrade'])->name('upgrade');
Route::post('/upgrade', [WebAuthController::class, 'upgrade'])->name('upgrade.post');

// End guest session
Route::post('/guest/end', [WebAuthController::class, 'endGuest'])->name('guest.end');
