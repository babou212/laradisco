<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\TypingController;
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

    Route::post('presence', [PresenceController::class, 'update'])->name('presence.update');
});

require __DIR__.'/settings.php';
