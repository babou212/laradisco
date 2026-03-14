<?php

use App\Http\Controllers\Api\MentionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/ping', fn () => response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'version' => app()->version(),
        'reverb' => [
            'key' => config('reverb.apps.apps.0.key', env('REVERB_APP_KEY')),
            'host' => env('REVERB_HOST', 'localhost'),
            'port' => (int) env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ],
    ]));

    Route::prefix('auth')->as('api.auth.')->group(
        base_path('routes/api/auth.php'),
    );

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Support\Facades\Broadcast::auth($request);
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

        Route::prefix('settings')->as('api.settings.')->group(
            base_path('routes/api/settings.php'),
        );

        Route::get('/users/{user}', [UserController::class, 'show'])->name('api.users.show');

        Route::get('/mentions/search', [MentionController::class, 'search'])->name('api.mentions.search');

        Route::prefix('e2ee')->as('api.e2ee.')->group(
            base_path('routes/api/e2ee.php'),
        );
    });

});
