<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\MentionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TypingController;
use App\Http\Controllers\VoiceChannelController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('setup', [SetupController::class, 'show'])->name('setup');
    Route::post('setup', [SetupController::class, 'complete'])->name('setup.complete');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat');
    Route::redirect('/home', '/')->name('home');
    Route::redirect('dashboard', '/')->name('dashboard');

    Route::post('channels/{channel}/messages', [MessageController::class, 'store'])->name('channels.messages.store');
    Route::put('channels/{channel}/messages/{message}', [MessageController::class, 'update'])->name('channels.messages.update');
    Route::delete('channels/{channel}/messages/{message}', [MessageController::class, 'destroy'])->name('channels.messages.destroy');

    Route::post('channels/{channel}/messages/{message}/reactions', [ReactionController::class, 'toggle'])->name('channels.messages.reactions.toggle');

    Route::post('channels/{channel}/typing', TypingController::class)->name('channels.typing');

    // Voice Channels
    Route::post('channels/{channel}/voice/join', [VoiceChannelController::class, 'join'])->name('channels.voice.join');
    Route::post('channels/{channel}/voice/leave', [VoiceChannelController::class, 'leave'])->name('channels.voice.leave');

    // Direct Messages
    Route::get('direct-message', [DirectMessageController::class, 'index'])->name('direct-message.index');
    Route::get('direct-message/{dmGroup}', [DirectMessageController::class, 'show'])->name('direct-message.show');
    Route::post('direct-message/{dmGroup}/messages', [DirectMessageController::class, 'store'])->name('direct-message.messages.store');
    Route::put('direct-message/{dmGroup}/messages/{message}', [DirectMessageController::class, 'update'])->name('direct-message.messages.update');
    Route::delete('direct-message/{dmGroup}/messages/{message}', [DirectMessageController::class, 'destroy'])->name('direct-message.messages.destroy');
    Route::post('direct-message/{dmGroup}/messages/{message}/reactions', [ReactionController::class, 'dmToggle'])->name('direct-message.messages.reactions.toggle');
    Route::post('direct-message/{dmGroup}/typing', [TypingController::class, 'dmTyping'])->name('direct-message.typing');
    Route::post('direct-message/start', [DirectMessageController::class, 'startOrGetDm'])->name('direct-message.start');

    Route::post('presence', [PresenceController::class, 'update'])->name('presence.update');

    // Mentions autocomplete
    Route::get('api/mentions/search', [MentionController::class, 'search'])->name('api.mentions.search');

    // Notifications
    Route::get('api/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::post('api/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.markAsRead');
    Route::post('api/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.markAllAsRead');

    // API routes for user data
    Route::get('api/users/{user}', function (User $user) {
        return response()->json($user);
    })->name('api.users.show');

    Route::get('search', [App\Http\Controllers\SearchController::class, 'index'])->name('search');
});

require __DIR__.'/settings.php';
