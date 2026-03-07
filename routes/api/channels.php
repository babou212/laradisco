<?php

use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\TypingController;
use App\Http\Controllers\Api\VoiceChannelController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [ChatController::class, 'categories'])
    ->middleware('cache.headers')
    ->name('categories.index');
Route::get('/members', [ChatController::class, 'members'])->name('members.index');

Route::get('/voice/participants', [VoiceChannelController::class, 'participants'])->name('voice.participants');

Route::prefix('channels/{channel}')->as('channels.')->group(function () {
    Route::get('/', [ChannelController::class, 'show'])
        ->middleware('cache.headers')
        ->name('show');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'store'])
        ->middleware(['throttle:api-messages', 'idempotency'])
        ->name('messages.store');
    Route::put('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

    Route::post('/messages/{message}/reactions', [ReactionController::class, 'toggle'])->name('messages.reactions.toggle');

    Route::post('/typing', TypingController::class)->name('typing');

    Route::post('/voice/join', [VoiceChannelController::class, 'join'])->name('voice.join');
    Route::delete('/voice/membership', [VoiceChannelController::class, 'leave'])->name('voice.leave');
});
