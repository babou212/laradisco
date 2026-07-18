<?php

use App\Http\Controllers\Api\E2EE\AuditLogController;
use App\Http\Controllers\Api\E2EE\DeviceController;
use App\Http\Controllers\Api\E2EE\IdentityController;
use App\Http\Controllers\Api\E2EE\KeyBackupController;
use App\Http\Controllers\Api\E2EE\MlsGroupController;
use App\Http\Controllers\Api\E2EE\MlsJoinController;
use App\Http\Controllers\Api\E2EE\MlsKeyPackageController;
use App\Http\Controllers\Api\E2EE\MlsMessageController;
use App\Http\Controllers\Api\E2EE\MlsWelcomeController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->group(function () {
    Route::post('identity/register', [IdentityController::class, 'register'])->name('identity.register');
    Route::delete('identity/reset', [IdentityController::class, 'reset'])->name('identity.reset');
});
Route::get('identity/{user}', [IdentityController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('identity.show');

Route::middleware('throttle:20,1')->group(function () {
    Route::post('devices/register', [DeviceController::class, 'register'])->name('devices.register');
});
Route::middleware('throttle:30,1')->group(function () {
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('devices/bind', [DeviceController::class, 'bind'])->name('devices.bind');
    Route::delete('devices/{deviceId}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::put('devices/{deviceId}/name', [DeviceController::class, 'updateName'])->name('devices.updateName');
});

Route::get('keys/backup/exists', [KeyBackupController::class, 'exists'])
    ->middleware('throttle:30,1')
    ->name('keys.backup.exists');

Route::middleware('throttle:15,1')->group(function () {
    Route::post('keys/backup', [KeyBackupController::class, 'store'])->name('keys.backup.store');
    Route::get('keys/backup', [KeyBackupController::class, 'show'])->name('keys.backup.show');
    Route::put('keys/backup', [KeyBackupController::class, 'update'])->name('keys.backup.update');
    Route::delete('keys/backup', [KeyBackupController::class, 'destroy'])->name('keys.backup.destroy');
    Route::post('keys/backup/confirm', [KeyBackupController::class, 'confirmRestore'])->name('keys.backup.confirm');
    Route::post('keys/backup/failed', [KeyBackupController::class, 'reportFailure'])->name('keys.backup.failed');
});

Route::post('keys/backup/unlock', [KeyBackupController::class, 'unlock'])
    ->middleware('throttle:5,1')
    ->name('keys.backup.unlock');

Route::middleware('throttle:30,1')->group(function () {
    Route::post('mls/key-packages', [MlsKeyPackageController::class, 'upload'])->name('mls.keyPackages.upload');
});
Route::get('mls/key-packages/count', [MlsKeyPackageController::class, 'count'])
    ->middleware('throttle:60,1')
    ->name('mls.keyPackages.count');
Route::get('mls/key-packages/{user}', [MlsKeyPackageController::class, 'fetch'])
    ->middleware('throttle:60,1')
    ->name('mls.keyPackages.fetch');

// MLS write endpoints (epoch-advancing commits, welcomes, claims) are the
// most abusable — every route below is rate limited.
Route::middleware('throttle:60,1')->group(function () {
    Route::post('mls/groups/{groupId}/messages', [MlsMessageController::class, 'submit'])->name('mls.groups.messages.submit');
    Route::post('mls/groups/{groupId}/claim', [MlsGroupController::class, 'claim'])->name('mls.groups.claim');
    Route::post('mls/groups/{groupId}/join-request', [MlsJoinController::class, 'requestJoin'])->name('mls.groups.joinRequest');
    Route::post('mls/groups/{groupId}/join-request/fulfill', [MlsJoinController::class, 'fulfill'])->name('mls.groups.joinRequest.fulfill');
    Route::get('mls/groups/{groupId}/join-requests', [MlsJoinController::class, 'pending'])->name('mls.groups.joinRequests.pending');
    Route::post('mls/groups/{groupId}/welcome', [MlsWelcomeController::class, 'submit'])->name('mls.groups.welcome.submit');
});

Route::middleware('throttle:120,1')->group(function () {
    Route::get('mls/groups/{groupId}/messages', [MlsMessageController::class, 'fetch'])->name('mls.groups.messages.fetch');
    Route::get('mls/groups/{groupId}/status', [MlsGroupController::class, 'status'])->name('mls.groups.status');
    Route::get('mls/welcome', [MlsWelcomeController::class, 'fetch'])->name('mls.welcome.fetch');
    Route::get('mls/user-groups', [MlsGroupController::class, 'userGroups'])->name('mls.userGroups');
    Route::get('channels/{channel}/members/bundles', [MlsGroupController::class, 'channelMemberBundles'])->name('channels.members.bundles');
    Route::get('dm-groups/{dmGroup}/members/bundles', [MlsGroupController::class, 'dmMemberBundles'])->name('dmGroups.members.bundles');
});

Route::middleware('throttle:30,1')->group(function () {
    Route::get('audit-log/{user}', [AuditLogController::class, 'index'])->name('auditLog.index');
    Route::get('audit-log/{user}/latest-hash', [AuditLogController::class, 'latestHash'])->name('auditLog.latestHash');
});
