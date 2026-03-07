<?php

use App\Http\Controllers\Api\DirectMessageController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\TypingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DirectMessageController::class, 'index'])->name('index');
Route::get('/find', [DirectMessageController::class, 'findDm'])->name('find');
Route::post('/', [DirectMessageController::class, 'createDm'])
    ->middleware('idempotency')
    ->name('create');

Route::prefix('{dmGroup}')->group(function () {
    Route::get('/', [DirectMessageController::class, 'show'])->name('show');

    Route::post('/messages', [DirectMessageController::class, 'store'])
        ->middleware(['throttle:api-messages', 'idempotency'])
        ->name('messages.store');
    Route::put('/messages/{message}', [DirectMessageController::class, 'update'])->name('messages.update');
    Route::delete('/messages/{message}', [DirectMessageController::class, 'destroy'])->name('messages.destroy');

    Route::post('/messages/{message}/reactions', [ReactionController::class, 'dmToggle'])->name('messages.reactions.toggle');

    Route::post('/typing', [TypingController::class, 'dmTyping'])->name('typing');
});
