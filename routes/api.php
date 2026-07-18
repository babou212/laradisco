<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\LiveKitWebhookController;
use App\Http\Controllers\Api\MentionController;
use App\Http\Controllers\Api\ServerSettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/ping', fn () => response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'reverb' => [
            'key' => config('reverb.apps.apps.0.key'),
            'host' => config('reverb.apps.apps.0.options.host'),
            'port' => (int) config('reverb.apps.apps.0.options.port', 443),
            'scheme' => config('reverb.apps.apps.0.options.scheme', 'https'),
        ],
    ]));

    Route::prefix('auth')->as('api.auth.')->group(
        base_path('routes/api/auth.php'),
    );

    Route::post('/livekit/webhook', [LiveKitWebhookController::class, 'handle'])
        ->name('api.livekit.webhook');

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/broadcasting/auth', function (Request $request) {
            return Broadcast::auth($request);
        });

        Route::as('api.')->group(
            base_path('routes/api/channels.php'),
        );

        Route::prefix('direct-messages')->as('api.direct-messages.')->group(
            base_path('routes/api/direct-messages.php'),
        );

        Route::prefix('presence')->as('api.presence.')->group(
            base_path('routes/api/presence.php'),
        );

        Route::prefix('notifications')->as('api.notifications.')->group(
            base_path('routes/api/notifications.php'),
        );

        Route::prefix('inbox')->as('api.inbox.')->group(
            base_path('routes/api/inbox.php'),
        );

        Route::prefix('settings')->as('api.settings.')->group(
            base_path('routes/api/settings.php'),
        );

        Route::prefix('soundboard')->as('api.soundboard.')->group(
            base_path('routes/api/soundboard.php'),
        );

        Route::prefix('e2ee')->as('api.e2ee.')->group(
            base_path('routes/api/e2ee.php'),
        );

        Route::get('/users/{user}', [UserController::class, 'show'])->name('api.users.show');

        Route::get('/users/{user}/avatar/{version}/{size}', [UserController::class, 'avatar'])
            ->whereIn('size', ['thumb', 'small', 'medium', 'original'])
            ->name('api.users.avatar');

        Route::get('/server/logo/{version}/{size}', [ServerSettingController::class, 'streamLogo'])
            ->whereIn('size', ['thumb', 'medium', 'original'])
            ->name('api.server.logo');

        Route::get('/mentions/search', [MentionController::class, 'search'])->name('api.mentions.search');

        Route::get('/attachments/{uuid}/download', [AttachmentController::class, 'download'])->name('api.attachments.download');
    });

});
