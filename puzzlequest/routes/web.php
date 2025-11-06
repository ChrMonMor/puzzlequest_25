<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\WebRunController;

Route::get('/', function () {
    return view('welcome');
});

// Web auth routes
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login'])->name('login.post');

Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [WebAuthController::class, 'register'])->name('register.post');

// Verification page (web) - calls API verify endpoint and renders a friendly page
Route::get('/verify-email', [WebAuthController::class, 'verifyEmail'])->name('verify.email');

Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// Guest creation
Route::post('/guest', [WebAuthController::class, 'createGuest'])->name('guest.create');
Route::get('/guest', [WebAuthController::class, 'showGuest'])->name('guest');

// Upgrade guest to full user (session-based)
Route::get('/upgrade', [WebAuthController::class, 'showUpgrade'])->name('upgrade');
Route::post('/upgrade', [WebAuthController::class, 'upgrade'])->name('upgrade.post');

// End guest session
Route::post('/guest/end', [WebAuthController::class, 'endGuest'])->name('guest.end');

// Map and Runs pages (web UI)
// Public: runs listing and show - guests can view
Route::get('/runs', [WebRunController::class, 'index'])->name('runs.index');
Route::get('/runs/{id}', [WebRunController::class, 'show'])->name('runs.show');

// Protected routes for authenticated users only
Route::middleware('auth')->group(function () {
    // Map view (uses resources/views/map.blade.php)
    Route::get('/map', function () { return view('map'); })->name('map');

    // Edit page placeholder (only linked when owner)
    Route::get('/runs/{id}/edit', [WebRunController::class, 'edit'])->name('runs.edit');
    // My runs listing
    Route::get('/my-runs', [WebRunController::class, 'myRuns'])->name('runs.mine');
});
