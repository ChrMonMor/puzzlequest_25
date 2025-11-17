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

// Password reset routes
Route::get('/forgot-password', [WebAuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [WebAuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password', [WebAuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [WebAuthController::class, 'resetPassword'])->name('password.update');

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
    // User profile
    Route::get('/profile', [WebAuthController::class, 'me'])->name('profile');
        Route::get('/profile/edit', [WebAuthController::class, 'showEditProfile'])->name('profile.edit');
        Route::put('/profile/update', [WebAuthController::class, 'updateProfile'])->name('profile.update');
        Route::get('/profile/change-password', [WebAuthController::class, 'showChangePassword'])->name('password.change.form');
        Route::put('/profile/change-password', [WebAuthController::class, 'changePassword'])->name('password.change');
    Route::delete('/profile/delete', [WebAuthController::class, 'deleteAccount'])->name('delete.account');

    // Map view (uses resources/views/map.blade.php)
    Route::get('/map', function () { return view('map'); })->name('map');

    // Edit page placeholder (only linked when owner)
    Route::get('/runs/{id}/edit', [WebRunController::class, 'edit'])->name('runs.edit');
    // Live runners map (owner only)
    Route::get('/runs/{id}/live', [WebRunController::class, 'live'])->name('runs.live');
    Route::get('/runs/{id}/live/data', [WebRunController::class, 'liveData'])->name('runs.live.data');
    // My runs listing
    Route::get('/my-runs', [WebRunController::class, 'myRuns'])->name('runs.mine');

    // Stats pages: users' histories
    // Removed public listing of users' histories (privacy): keep per-user and per-run stats routes only
    Route::get('/stats/{userId}', [App\Http\Controllers\WebStatsController::class, 'show'])->name('stats.show');
    // Run-level stats (owner-only)
    Route::get('/runs/{runId}/stats', [App\Http\Controllers\WebStatsController::class, 'run'])->name('stats.run');
    // View a single history (detailed view for a run play-through)
    Route::get('/history/{historyId}', [App\Http\Controllers\WebHistoryController::class, 'show'])->name('history.show');
});
