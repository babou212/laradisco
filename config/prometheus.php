<?php

use Spatie\Prometheus\Actions\RenderCollectorsAction;
use Spatie\Prometheus\Http\Middleware\AllowIps;

return [
    'enabled' => env('PROMETHEUS_ENABLED', true),

    'urls' => [
        'default' => 'prometheus',
    ],

    'allowed_ips' => array_filter(explode(',', (string) env('PROMETHEUS_ALLOWED_IPS', ''))),

    'default_namespace' => 'laradisco',

    'middleware' => [
        AllowIps::class,
    ],

    'actions' => [
        'render_collectors' => RenderCollectorsAction::class,
    ],

    'wipe_storage_after_rendering' => false,

    'cache' => env('PROMETHEUS_CACHE_STORE', 'redis'),
];
