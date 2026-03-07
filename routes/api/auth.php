<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login');

Route::post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])
    ->middleware('throttle:5,1')
    ->name('two-factor-challenge');

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:5,1')
    ->name('forgot-password');

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:5,1')
    ->name('reset-password');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'user'])->name('me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
