<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Prometheus\CollectorRegistry;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMetrics
{
    protected const NAMESPACE = 'laradisco';

    protected int $queryCount = 0;

    protected float $queryTotalTime = 0;

    protected int $slowQueryCount = 0;

    protected float $slowQueryThreshold;

    public function __construct(
        protected CollectorRegistry $registry,
    ) {
        $this->slowQueryThreshold = (float) config('prometheus.slow_query_threshold_ms', 100);
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $this->listenForQueries();

        $response = $next($request);

        $durationSeconds = microtime(true) - $start;
        $labels = $this->extractLabels($request, $response);

        $this->recordRequestDuration($durationSeconds, $labels);
        $this->recordRequestCount($labels);
        $this->recordQueryMetrics($labels);

        return $response;
    }

    protected function listenForQueries(): void
    {
        DB::listen(function ($query): void {
            $this->queryCount++;
            $this->queryTotalTime += $query->time;

            if ($query->time >= $this->slowQueryThreshold) {
                $this->slowQueryCount++;
            }
        });
    }

    /**
     * @return array{string, string, string}
     */
    protected function extractLabels(Request $request, Response $response): array
    {
        $route = $request->route();
        $routeName = $route?->uri() ?? 'unknown';

        // Normalize route to avoid high cardinality from path parameters
        $routeName = preg_replace('#\{[^}]+\}#', '{id}', $routeName);

        return [
            $request->method(),
            $routeName,
            (string) $response->getStatusCode(),
        ];
    }

    protected function recordRequestDuration(float $durationSeconds, array $labels): void
    {
        $histogram = $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            ['method', 'route', 'status'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],
        );

        $histogram->observe($durationSeconds, $labels);
    }

    protected function recordRequestCount(array $labels): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'http_requests_total',
            'Total number of HTTP requests',
            ['method', 'route', 'status'],
        );

        $counter->inc($labels);
    }

    protected function recordQueryMetrics(array $labels): void
    {
        $routeLabels = [$labels[0], $labels[1]]; // method, route only

        // Query duration histogram
        if ($this->queryTotalTime > 0) {
            $histogram = $this->registry->getOrRegisterHistogram(
                self::NAMESPACE,
                'db_query_duration_milliseconds',
                'Database query duration in milliseconds (aggregate per request)',
                ['method', 'route'],
                [1, 5, 10, 25, 50, 100, 250, 500, 1000, 2500],
            );

            $histogram->observe($this->queryTotalTime, $routeLabels);
        }

        // Slow query counter
        if ($this->slowQueryCount > 0) {
            $counter = $this->registry->getOrRegisterCounter(
                self::NAMESPACE,
                'db_slow_queries_total',
                'Total number of slow database queries (>= threshold)',
                ['method', 'route'],
            );

            $counter->incBy($this->slowQueryCount, $routeLabels);
        }

        // Queries per request histogram (N+1 detection)
        $histogram = $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'db_queries_per_request',
            'Number of database queries executed per request',
            ['method', 'route'],
            [1, 2, 5, 10, 20, 50, 100, 200],
        );

        $histogram->observe($this->queryCount, $routeLabels);
    }
}
