<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::get('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth.api'])->group(function () {
    Route::post('/user', [AuthController::class, 'me']);
    Route::patch('/update-profile', [AuthController::class, 'updateProfile']);
    Route::delete('/delete', [AuthController::class, 'deleteAccount']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['auth.api', 'refresh.token'])->post('/user', [AuthController::class, 'me']);
