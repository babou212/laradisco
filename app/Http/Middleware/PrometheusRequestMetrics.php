<?php

namespace App\Http\Middleware;

use App\Support\Metrics\MetricsRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrometheusRequestMetrics
{
    private const SKIP_PATHS = ['prometheus', 'up'];

    private const START_ATTRIBUTE = '_prometheus_start';

    public function __construct(private readonly MetricsRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        $request->attributes->set(self::START_ATTRIBUTE, microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->shouldSkip($request)) {
            return;
        }

        $start = (float) $request->attributes->get(self::START_ATTRIBUTE, microtime(true));
        $duration = max(0.0, microtime(true) - $start);

        try {
            $this->recorder->recordHttpRequest(
                method: $request->getMethod(),
                route: $this->routeLabel($request),
                status: $response->getStatusCode(),
                durationSeconds: $duration,
                memoryBytes: memory_get_peak_usage(true),
            );
        } catch (Throwable) {
        }
    }

    private function shouldSkip(Request $request): bool
    {
        return in_array($request->path(), self::SKIP_PATHS, true);
    }

    private function routeLabel(Request $request): string
    {
        $route = $request->route();

        if ($route === null) {
            return 'unmatched';
        }

        $name = method_exists($route, 'getName') ? $route->getName() : null;
        $uri = method_exists($route, 'uri') ? $route->uri() : null;

        return $name ?? ($uri ? '/'.ltrim($uri, '/') : 'unmatched');
    }
}
