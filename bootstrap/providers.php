<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PresenceEventServiceProvider;
use App\Providers\PrometheusServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    RateLimitServiceProvider::class,
    PresenceEventServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    PrometheusServiceProvider::class,
];
