<?php

use App\Http\Controllers\Api\E2EE\AuditLogController;
use App\Http\Controllers\Api\E2EE\DeviceController;
use App\Http\Controllers\Api\E2EE\IdentityController;
use App\Http\Controllers\Api\E2EE\KeyBackupController;
use App\Http\Controllers\Api\E2EE\MlsController;
use App\Http\Controllers\Api\E2EE\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->group(function () {
    Route::post('identity/register', [IdentityController::class, 'register'])->name('identity.register');
    Route::delete('identity/reset', [IdentityController::class, 'reset'])->name('identity.reset');
});
Route::get('identity/{user}', [IdentityController::class, 'show'])->name('identity.show');

Route::middleware('throttle:20,1')->group(function () {
    Route::post('devices/register', [DeviceController::class, 'register'])->name('devices.register');
});
Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
Route::delete('devices/{deviceId}', [DeviceController::class, 'destroy'])->name('devices.destroy');
Route::put('devices/{deviceId}/name', [DeviceController::class, 'updateName'])->name('devices.updateName');

Route::get('keys/backup/exists', [KeyBackupController::class, 'exists'])->name('keys.backup.exists');

Route::middleware('throttle:15,1')->group(function () {
    Route::post('keys/backup', [KeyBackupController::class, 'store'])->name('keys.backup.store');
    Route::get('keys/backup', [KeyBackupController::class, 'show'])->name('keys.backup.show');
    Route::put('keys/backup', [KeyBackupController::class, 'update'])->name('keys.backup.update');
    Route::delete('keys/backup', [KeyBackupController::class, 'destroy'])->name('keys.backup.destroy');
});

Route::middleware('throttle:30,1')->group(function () {
    Route::post('mls/key-packages', [MlsController::class, 'uploadKeyPackages'])->name('mls.keyPackages.upload');
});
Route::get('mls/key-packages/count', [MlsController::class, 'keyPackageCount'])->name('mls.keyPackages.count');
Route::get('mls/key-packages/{user}', [MlsController::class, 'fetchKeyPackages'])->name('mls.keyPackages.fetch');

Route::post('mls/groups/{groupId}/messages', [MlsController::class, 'submitMessage'])->name('mls.groups.messages.submit');
Route::get('mls/groups/{groupId}/messages', [MlsController::class, 'fetchMessages'])->name('mls.groups.messages.fetch');

Route::post('mls/groups/{groupId}/welcome', [MlsController::class, 'submitWelcome'])->name('mls.groups.welcome.submit');
Route::get('mls/welcome', [MlsController::class, 'fetchWelcomes'])->name('mls.welcome.fetch');

Route::get('channels/{channel}/members/bundles', [MlsController::class, 'channelMemberBundles'])->name('channels.members.bundles');
Route::get('dm-groups/{dmGroup}/members/bundles', [MlsController::class, 'dmMemberBundles'])->name('dmGroups.members.bundles');

Route::middleware('throttle:60,1')->group(function () {
    Route::post('search', [SearchController::class, 'search'])->name('search');
});

Route::get('audit-log/{user}', [AuditLogController::class, 'index'])->name('auditLog.index');
Route::get('audit-log/{user}/latest-hash', [AuditLogController::class, 'latestHash'])->name('auditLog.latestHash');
