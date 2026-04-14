<?php

namespace App\Providers;

use App\Enums\UserStatusType;
use App\Events\UserPresenceUpdated;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PresenceEventServiceProvider extends ServiceProvider
{
    public function boot(): void
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
}
