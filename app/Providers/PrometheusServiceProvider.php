<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
    }
}
