<?php

namespace App\Providers;

use App\Listeners\PrometheusEventSubscriber;
use App\Support\Metrics\MetricsRecorder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis as RedisStorage;
use Spatie\Prometheus\Collectors\Horizon\CurrentMasterSupervisorCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentProcessesPerQueueCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentWorkloadCollector;
use Spatie\Prometheus\Collectors\Horizon\FailedJobsPerHourCollector;
use Spatie\Prometheus\Collectors\Horizon\FailedRecentJobsCollector;
use Spatie\Prometheus\Collectors\Horizon\HorizonStatusCollector;
use Spatie\Prometheus\Collectors\Horizon\JobsPerMinuteCollector;
use Spatie\Prometheus\Collectors\Horizon\RecentJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueDelayedJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueOldestPendingJobCollector;
use Spatie\Prometheus\Collectors\Queue\QueuePendingJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueReservedJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueSizeCollector;
use Spatie\Prometheus\Facades\Prometheus;

class PrometheusServiceProvider extends ServiceProvider
{
    private const QUEUES = ['default', 'broadcasting', 'notifications', 'media'];

    private const HORIZON_COLLECTORS = [
        CurrentMasterSupervisorCollector::class,
        CurrentProcessesPerQueueCollector::class,
        CurrentWorkloadCollector::class,
        FailedJobsPerHourCollector::class,
        FailedRecentJobsCollector::class,
        HorizonStatusCollector::class,
        JobsPerMinuteCollector::class,
        RecentJobsCollector::class,
    ];

    private const QUEUE_COLLECTORS = [
        QueueDelayedJobsCollector::class,
        QueueOldestPendingJobCollector::class,
        QueuePendingJobsCollector::class,
        QueueReservedJobsCollector::class,
        QueueSizeCollector::class,
    ];

    public function register(): void
    {
        $this->app->singleton(Adapter::class, function () {
            $redis = config('database.redis.default');

            if ($redis === null || ! extension_loaded('redis')) {
                return new InMemory;
            }

            RedisStorage::setDefaultOptions([
                'host' => $redis['host'] ?? '127.0.0.1',
                'port' => (int) ($redis['port'] ?? 6379),
                'password' => $redis['password'] ?? null,
                'timeout' => 0.5,
                'read_timeout' => 5,
                'persistent_connections' => false,
                'database' => (int) env('REDIS_PROMETHEUS_DB', 4),
            ]);

            return new RedisStorage;
        });

        $this->app->singleton(CollectorRegistry::class, function (Application $app) {
            return new CollectorRegistry($app->make(Adapter::class), false);
        });

        $this->app->singleton(MetricsRecorder::class, function (Application $app) {
            return new MetricsRecorder($app->make(CollectorRegistry::class));
        });

        $this->app->singleton(PrometheusEventSubscriber::class, function (Application $app) {
            return new PrometheusEventSubscriber($app->make(MetricsRecorder::class));
        });
    }

    public function boot(): void
    {
        if (! config('prometheus.enabled')) {
            return;
        }

        Prometheus::registerCollectorClasses(self::HORIZON_COLLECTORS);

        Prometheus::registerCollectorClasses(
            self::QUEUE_COLLECTORS,
            ['redis', self::QUEUES],
        );

        Event::subscribe(PrometheusEventSubscriber::class);
    }
}
