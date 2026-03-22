<?php

namespace App\Providers;

use App\Enums\PermissionFlag;
use App\Enums\UserStatusType;
use App\Events\UserPresenceUpdated;
use App\Models\User;
use App\Services\LiveKitService;
use App\Services\PresenceService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureGates();
        $this->configureAuthListeners();
        $this->configureRateLimiting();
    }

    /**
     * Register Gate abilities for cross-model authorization.
     */
    protected function configureGates(): void
    {
        Gate::define('manage-members', function (User $user): bool {
            return $user->isAdministrator() || $user->hasPermission(PermissionFlag::ManageRoles);
        });

        Gate::define('viewPulse', function (?User $user): bool {
            return true; // Protected by oauth2-proxy at infrastructure level
        });
    }

    /**
     * Register authentication event listeners.
     */
    protected function configureAuthListeners(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            /** @var User $user */
            $user = $event->user;

            $user->update(['status' => UserStatusType::Online->value]);

            app(PresenceService::class)->register($user);

            event(new UserPresenceUpdated(
                $user,
                UserStatusType::Online,
                $user->custom_status,
            ));
        });

        Event::listen(Logout::class, function (Logout $event): void {
            /** @var User $user */
            $user = $event->user;
            app(PresenceService::class)->unregister($user);

            event(new UserPresenceUpdated(
                $user,
                UserStatusType::Offline,
            ));
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

    /**
     * Configure rate limiters for the API.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        RateLimiter::for('api-messages', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-search', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });
    }
}
