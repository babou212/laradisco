<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\TypingController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat');

    Route::get('dashboard', function () {
        return redirect()->route('chat');
    })->name('dashboard');

    Route::get('channels/{channel}', [ChannelController::class, 'show'])->name('channels.show');
    Route::post('channels/{channel}/messages', [MessageController::class, 'store'])->name('channels.messages.store');
    Route::put('channels/{channel}/messages/{message}', [MessageController::class, 'update'])->name('channels.messages.update');
    Route::delete('channels/{channel}/messages/{message}', [MessageController::class, 'destroy'])->name('channels.messages.destroy');

    Route::post('channels/{channel}/messages/{message}/reactions', [ReactionController::class, 'toggle'])->name('channels.messages.reactions.toggle');

    Route::post('channels/{channel}/typing', TypingController::class)->name('channels.typing');

    // Direct Messages
    Route::get('direct-message', [DirectMessageController::class, 'index'])->name('direct-message.index');
    Route::get('direct-message/{dmGroup}', [DirectMessageController::class, 'show'])->name('direct-message.show');
    Route::post('direct-message/{dmGroup}/messages', [DirectMessageController::class, 'store'])->name('direct-message.messages.store');
    Route::put('direct-message/{dmGroup}/messages/{message}', [DirectMessageController::class, 'update'])->name('direct-message.messages.update');
    Route::delete('direct-message/{dmGroup}/messages/{message}', [DirectMessageController::class, 'destroy'])->name('direct-message.messages.destroy');
    Route::post('direct-message/{dmGroup}/typing', [TypingController::class, 'dmTyping'])->name('direct-message.typing');
    Route::post('direct-message/start', [DirectMessageController::class, 'startOrGetDm'])->name('direct-message.start');

    Route::post('presence', [PresenceController::class, 'update'])->name('presence.update');

    // API routes for user data
    Route::get('api/users/{user}', function (User $user) {
        return response()->json($user);
    })->name('api.users.show');

    Route::get('search', [App\Http\Controllers\SearchController::class, 'index'])->name('search');
});

require __DIR__.'/settings.php';
