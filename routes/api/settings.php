<?php

use App\Http\Controllers\Api\Settings\CategoryController;
use App\Http\Controllers\Api\Settings\ChannelController;
use App\Http\Controllers\Api\Settings\InviteLinkController;
use App\Http\Controllers\Api\Settings\MemberController;
use App\Http\Controllers\Api\Settings\PasswordController;
use App\Http\Controllers\Api\Settings\ProfileController;
use App\Http\Controllers\Api\Settings\RoleController;
use App\Http\Controllers\Api\Settings\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/profile', [ProfileController::class, 'show'])
    ->middleware('cache.headers')
    ->name('profile.show');
Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

Route::put('/password', [PasswordController::class, 'update'])
    ->middleware('throttle:6,1')
    ->name('password.update');

Route::prefix('two-factor')->as('two-factor.')->group(function () {
    Route::get('/', [TwoFactorController::class, 'show'])->name('show');
    Route::post('/enable', [TwoFactorController::class, 'enable'])->name('enable');
    Route::delete('/disable', [TwoFactorController::class, 'disable'])->name('disable');
    Route::get('/qr-code', [TwoFactorController::class, 'qrCode'])->name('qr-code');
    Route::get('/secret-key', [TwoFactorController::class, 'secretKey'])->name('secret-key');
    Route::get('/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('recovery-codes');
    Route::post('/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
    Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
});

Route::apiResource('invite-links', InviteLinkController::class)->only(['index', 'destroy']);
Route::post('/invite-links', [InviteLinkController::class, 'store'])
    ->middleware('idempotency')
    ->name('invite-links.store');

Route::apiResource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);

Route::get('/members', [MemberController::class, 'index'])->name('members.index');
Route::post('/members/{user}/roles', [MemberController::class, 'assignRole'])->name('members.roles.assign');
Route::delete('/members/{user}/roles/{role}', [MemberController::class, 'removeRole'])->name('members.roles.remove')->scopeBindings();

Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
Route::put('/channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
Route::delete('/channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');
Route::get('/channels/{channel}/overrides', [ChannelController::class, 'overrides'])->name('channels.overrides.index');
Route::post('/channels/{channel}/overrides', [ChannelController::class, 'storeOverride'])->name('channels.overrides.store');
Route::delete('/channels/{channel}/overrides/{override}', [ChannelController::class, 'destroyOverride'])->name('channels.overrides.destroy')->scopeBindings();

Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
