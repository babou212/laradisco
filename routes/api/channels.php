<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PinController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\SoundboardController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\TypingController;
use App\Http\Controllers\Api\VoiceChannelController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [ChatController::class, 'categories'])
    ->middleware('cache.headers')
    ->name('categories.index');
Route::get('/members', [ChatController::class, 'members'])->name('members.index');

Route::get('/voice/participants', [VoiceChannelController::class, 'participants'])->name('voice.participants');

Route::prefix('channels/{channel}')->as('channels.')->scopeBindings()->group(function () {
    Route::get('/', [ChannelController::class, 'show'])
        ->middleware('cache.headers')
        ->name('show');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/head', [MessageController::class, 'head'])->name('messages.head');
    Route::get('/messages/search', [MessageController::class, 'search'])->name('messages.search');
    Route::post('/messages', [MessageController::class, 'store'])
        ->middleware(['throttle:api-messages', 'idempotency'])
        ->name('messages.store');
    Route::post('/read', [MessageController::class, 'markRead'])->name('read');
    Route::put('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

    Route::post('/attachments/upload', [AttachmentController::class, 'uploadForChannel'])->name('attachments.upload');

    Route::post('/messages/{message}/reactions', [ReactionController::class, 'toggle'])->name('messages.reactions.toggle');

    Route::get('/pins', [PinController::class, 'index'])->name('pins.index');
    Route::post('/messages/{message}/pin', [PinController::class, 'pin'])->name('messages.pin');
    Route::delete('/messages/{message}/pin', [PinController::class, 'unpin'])->name('messages.unpin');

    Route::post('/typing', TypingController::class)->name('typing');

    Route::post('/messages/{message}/thread', [ThreadController::class, 'storeReply'])
        ->middleware(['throttle:api-messages', 'idempotency'])
        ->name('messages.thread.store');

    Route::prefix('threads/{thread}')->as('threads.')->scopeBindings()->group(function () {
        Route::get('/', [ThreadController::class, 'show'])->name('show');
        Route::get('/messages', [ThreadController::class, 'messages'])->name('messages');
        Route::put('/messages/{message}', [ThreadController::class, 'updateReply'])->name('messages.update');
        Route::delete('/messages/{message}', [ThreadController::class, 'destroyReply'])->name('messages.destroy');
        Route::post('/follow', [ThreadController::class, 'follow'])->name('follow');
        Route::delete('/follow', [ThreadController::class, 'unfollow'])->name('unfollow');

        Route::post('/messages/{message}/reactions', [ReactionController::class, 'threadToggle'])->name('messages.reactions.toggle');
        Route::post('/messages/{message}/pin', [PinController::class, 'pin'])->name('messages.pin');
        Route::delete('/messages/{message}/pin', [PinController::class, 'unpin'])->name('messages.unpin');
    });

    Route::post('/voice/join', [VoiceChannelController::class, 'join'])->name('voice.join');
    Route::get('/voice/key', [VoiceChannelController::class, 'key'])->name('voice.key');
    Route::delete('/voice/membership', [VoiceChannelController::class, 'leave'])->name('voice.leave');
    Route::post('/voice/soundboard/play', [SoundboardController::class, 'play'])->name('voice.soundboard.play');
});
