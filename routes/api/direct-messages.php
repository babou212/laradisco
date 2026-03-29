<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\DirectMessageController;
use App\Http\Controllers\Api\PinController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\TypingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DirectMessageController::class, 'index'])->name('index');
Route::get('/find', [DirectMessageController::class, 'findDm'])->name('find');
Route::post('/', [DirectMessageController::class, 'createDm'])
    ->middleware('idempotency')
    ->name('create');

Route::prefix('{dmGroup}')->scopeBindings()->group(function () {
    Route::get('/', [DirectMessageController::class, 'show'])->name('show');

    Route::post('/messages', [DirectMessageController::class, 'store'])
        ->middleware(['throttle:api-messages', 'idempotency'])
        ->name('messages.store');
    Route::put('/messages/{message}', [DirectMessageController::class, 'update'])->name('messages.update');
    Route::delete('/messages/{message}', [DirectMessageController::class, 'destroy'])->name('messages.destroy');

    Route::post('/attachments/presign', [AttachmentController::class, 'presignForDm'])->name('attachments.presign');

    Route::post('/messages/{message}/reactions', [ReactionController::class, 'dmToggle'])->name('messages.reactions.toggle');

    Route::get('/pins', [PinController::class, 'dmIndex'])->name('pins.index');
    Route::post('/messages/{message}/pin', [PinController::class, 'dmPin'])->name('messages.pin');
    Route::delete('/messages/{message}/pin', [PinController::class, 'dmUnpin'])->name('messages.unpin');

    Route::post('/typing', [TypingController::class, 'dmTyping'])->name('typing');
});
