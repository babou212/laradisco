<?php

namespace App\Providers;

use App\Enums\UserStatusType;
use App\Services\LiveKitService;
use App\Services\PresenceService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
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

            $user->update(['status' => UserStatusType::Online->value]);

            app(PresenceService::class)->register($user);

            event(new \App\Events\UserPresenceUpdated(
                $user,
                UserStatusType::Online,
                $user->custom_status,
            ));
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user) {
                $user = $event->user;
                app(PresenceService::class)->unregister($user);

                event(new \App\Events\UserPresenceUpdated(
                    $user,
                    UserStatusType::Offline,
                ));
            }
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
