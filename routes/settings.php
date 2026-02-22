<?php

use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\ChannelController;
use App\Http\Controllers\Settings\InviteLinkController;
use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');

    Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
    Route::patch('settings/notifications', [NotificationController::class, 'update'])->name('notifications.update');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/invite-links', [InviteLinkController::class, 'index'])->name('invite-links.index');
    Route::post('settings/invite-links', [InviteLinkController::class, 'store'])->name('invite-links.store');
    Route::delete('settings/invite-links/{inviteLink}', [InviteLinkController::class, 'destroy'])->name('invite-links.destroy');

    Route::get('settings/roles', [RoleController::class, 'index'])->name('settings.roles.index');
    Route::post('settings/roles', [RoleController::class, 'store'])->name('settings.roles.store');
    Route::put('settings/roles/{role}', [RoleController::class, 'update'])->name('settings.roles.update');
    Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('settings.roles.destroy');

    Route::get('settings/channels', [ChannelController::class, 'index'])->name('settings.channels.index');
    Route::post('settings/channels', [ChannelController::class, 'storeChannel'])->name('settings.channels.store');
    Route::put('settings/channels/{channel}', [ChannelController::class, 'updateChannel'])->name('settings.channels.update');
    Route::delete('settings/channels/{channel}', [ChannelController::class, 'destroyChannel'])->name('settings.channels.destroy');

    Route::post('settings/categories', [ChannelController::class, 'storeCategory'])->name('settings.categories.store');
    Route::put('settings/categories/{category}', [ChannelController::class, 'updateCategory'])->name('settings.categories.update');
    Route::delete('settings/categories/{category}', [ChannelController::class, 'destroyCategory'])->name('settings.categories.destroy');

    Route::get('settings/channels/{channel}/overrides', [ChannelController::class, 'getOverrides'])->name('settings.channels.overrides.index');
    Route::post('settings/channels/{channel}/overrides', [ChannelController::class, 'storeOverride'])->name('settings.channels.overrides.store');
    Route::delete('settings/channels/{channel}/overrides/{override}', [ChannelController::class, 'destroyOverride'])->name('settings.channels.overrides.destroy');

    Route::get('settings/members', [MemberController::class, 'index'])->name('settings.members.index');
    Route::post('settings/members/{user}/roles', [MemberController::class, 'assignRole'])->name('settings.members.assign-role');
    Route::delete('settings/members/{user}/roles', [MemberController::class, 'removeRole'])->name('settings.members.remove-role');
});
