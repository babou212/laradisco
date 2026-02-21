<?php

namespace App\Providers;

use App\Enums\UserStatusType;
use App\Services\LiveKitService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LiveKitService::class, function ($app): LiveKitService {
            return new LiveKitService(
                apiKey: config('livekit.api_key'),
                apiSecret: config('livekit.api_secret'),
                url: config('livekit.url'),
                tokenTtl: config('livekit.token_ttl'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthListeners();
    }

    /**
     * Register authentication event listeners.
     */
    protected function configureAuthListeners(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            /** @var \App\Models\User $user */
            $user = $event->user;

            // Reset status to online at the start of every new session
            // so the presence channel auth returns the correct status.
            $user->update(['status' => UserStatusType::Online->value]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
