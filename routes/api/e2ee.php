<?php

use App\Http\Controllers\Api\E2EE\AuditLogController;
use App\Http\Controllers\Api\E2EE\DeviceController;
use App\Http\Controllers\Api\E2EE\IdentityController;
use App\Http\Controllers\Api\E2EE\KeyBackupController;
use App\Http\Controllers\Api\E2EE\KeyController;
use App\Http\Controllers\Api\E2EE\DmSenderKeyController;
use App\Http\Controllers\Api\E2EE\SearchController;
use App\Http\Controllers\Api\E2EE\SenderKeyController;
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

Route::get('keys/{user}/bundle', [KeyController::class, 'bundle'])->name('keys.bundle');
Route::get('keys/{user}/devices', [KeyController::class, 'deviceList'])->name('keys.deviceList');
Route::middleware('throttle:30,1')->group(function () {
    Route::post('keys/prekeys/replenish', [KeyController::class, 'replenishPrekeys'])->name('keys.prekeys.replenish');
    Route::put('keys/signed-prekey', [KeyController::class, 'rotateSignedPrekey'])->name('keys.signedPrekey.rotate');
});
Route::get('keys/prekeys/count', [KeyController::class, 'prekeyCount'])->name('keys.prekeys.count');

Route::get('keys/backup/exists', [KeyBackupController::class, 'exists'])->name('keys.backup.exists');

Route::middleware('throttle:15,1')->group(function () {
    Route::post('keys/backup', [KeyBackupController::class, 'store'])->name('keys.backup.store');
    Route::get('keys/backup', [KeyBackupController::class, 'show'])->name('keys.backup.show');
    Route::put('keys/backup', [KeyBackupController::class, 'update'])->name('keys.backup.update');
    Route::delete('keys/backup', [KeyBackupController::class, 'destroy'])->name('keys.backup.destroy');
});

Route::post('channels/{channel}/sender-keys', [SenderKeyController::class, 'distribute'])->name('channels.senderKeys.distribute');
Route::get('channels/{channel}/sender-keys', [SenderKeyController::class, 'index'])->name('channels.senderKeys.index');
Route::delete('channels/{channel}/sender-keys', [SenderKeyController::class, 'invalidate'])->name('channels.senderKeys.invalidate');
Route::post('channels/{channel}/request-sender-keys', [SenderKeyController::class, 'requestKeys'])->name('channels.senderKeys.request');
Route::get('channels/{channel}/members/bundles', [SenderKeyController::class, 'memberBundles'])->name('channels.members.bundles');

Route::post('dm-groups/{dmGroup}/sender-keys', [DmSenderKeyController::class, 'distribute'])->name('dmGroups.senderKeys.distribute');
Route::get('dm-groups/{dmGroup}/sender-keys', [DmSenderKeyController::class, 'index'])->name('dmGroups.senderKeys.index');
Route::delete('dm-groups/{dmGroup}/sender-keys', [DmSenderKeyController::class, 'invalidate'])->name('dmGroups.senderKeys.invalidate');
Route::post('dm-groups/{dmGroup}/request-sender-keys', [DmSenderKeyController::class, 'requestKeys'])->name('dmGroups.senderKeys.request');
Route::get('dm-groups/{dmGroup}/members/bundles', [DmSenderKeyController::class, 'memberBundles'])->name('dmGroups.members.bundles');

Route::middleware('throttle:60,1')->group(function () {
    Route::post('search', [SearchController::class, 'search'])->name('search');
});

Route::get('audit-log/{user}', [AuditLogController::class, 'index'])->name('auditLog.index');
Route::get('audit-log/{user}/latest-hash', [AuditLogController::class, 'latestHash'])->name('auditLog.latestHash');
