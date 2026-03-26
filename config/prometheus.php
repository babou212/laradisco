<?php

return [
    'enabled' => true,

    /*
     * The urls that will return metrics.
     */
    'urls' => [
        'default' => 'prometheus',
    ],

    /*
     * Only these IP's will be allowed to visit the above urls.
     * All IP's are allowed when empty.
     *
     * In production, the /prometheus endpoint is not exposed via the Gateway.
     * Only cluster-internal Prometheus pods can reach it (enforced by NetworkPolicy).
     * This acts as an additional defense-in-depth layer.
     */
    'allowed_ips' => array_filter(explode(',', env('PROMETHEUS_ALLOWED_IPS', ''))),

    /*
     * This is the default namespace that will be
     * used by all metrics
     */
    'default_namespace' => 'laradisco',

    /*
     * The middleware that will be applied to the urls above
     */
    'middleware' => [
        Spatie\Prometheus\Http\Middleware\AllowIps::class,
    ],

    /*
     * You can override these classes to customize low-level behaviour of the package.
     * In most cases, you can just use the defaults.
     */
    'actions' => [
        'render_collectors' => Spatie\Prometheus\Actions\RenderCollectorsAction::class,
    ],

    /**
     * Allow storage to be wiped after a render of data in metrics controller.
     */
    'wipe_storage_after_rendering' => false,

    /**
     * Select a cache to store gauges, counters, summaries and histograms between requests.
     * Use 'redis' in production for persistence across requests.
     * Use null for in-memory (pull-based gauges that compute on scrape).
     */
    'cache' => env('PROMETHEUS_CACHE', 'redis'),

    /**
     * Slow query threshold in milliseconds.
     * Queries exceeding this duration are counted as slow.
     */
    'slow_query_threshold_ms' => env('PROMETHEUS_SLOW_QUERY_MS', 100),
];
