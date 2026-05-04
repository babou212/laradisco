<?php

namespace App\Support\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;
use Throwable;

class MetricsRecorder
{
    private const NAMESPACE = 'laradisco';

    private const HTTP_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

    private const QUERY_BUCKETS = [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5];

    private const JOB_BUCKETS = [0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30, 60, 300];

    private const OUTGOING_HTTP_BUCKETS = [0.01, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30];

    public function __construct(private readonly CollectorRegistry $registry) {}

    public function recordHttpRequest(string $method, string $route, int $status, float $durationSeconds, int $memoryBytes): void
    {
        $labels = [strtoupper($method), $route, (string) $status];

        $this->httpRequestsTotal()->incBy(1, $labels);
        $this->httpRequestDurationSeconds()->observe($durationSeconds, $labels);
        $this->httpRequestMemoryBytes()->observe($memoryBytes, [strtoupper($method), $route]);
    }

    public function recordQuery(string $connection, float $durationSeconds): void
    {
        $this->dbQueriesTotal()->incBy(1, [$connection]);
        $this->dbQueryDurationSeconds()->observe($durationSeconds, [$connection]);
    }

    public function recordCacheOperation(string $store, string $operation): void
    {
        $this->cacheOperationsTotal()->incBy(1, [$store, $operation]);
    }

    public function recordMail(string $transport): void
    {
        $this->mailSentTotal()->incBy(1, [$transport]);
    }

    public function recordNotification(string $channel): void
    {
        $this->notificationsSentTotal()->incBy(1, [$channel]);
    }

    public function recordException(Throwable $e): void
    {
        $this->exceptionsTotal()->incBy(1, [$this->normalizeClass($e::class)]);
    }

    public function recordOutgoingHttp(string $host, int $status, float $durationSeconds): void
    {
        $labels = [$host, (string) $status];
        $this->outgoingHttpRequestsTotal()->incBy(1, $labels);
        $this->outgoingHttpRequestDurationSeconds()->observe($durationSeconds, $labels);
    }

    public function recordJob(string $connection, string $queue, string $jobClass, string $status, float $durationSeconds): void
    {
        $labels = [$connection, $queue, $this->normalizeClass($jobClass), $status];
        $this->jobsProcessedTotal()->incBy(1, $labels);

        if ($status !== 'failed') {
            $this->jobDurationSeconds()->observe($durationSeconds, [$connection, $queue, $this->normalizeClass($jobClass)]);
        }
    }

    public function recordScheduledTask(string $task, string $status, float $durationSeconds): void
    {
        $this->scheduledTasksTotal()->incBy(1, [$task, $status]);
        $this->scheduledTaskDurationSeconds()->observe($durationSeconds, [$task]);
    }

    private function httpRequestsTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'http_requests_total',
            'Total HTTP requests handled',
            ['method', 'route', 'status'],
        );
    }

    private function httpRequestDurationSeconds(): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'http_request_duration_seconds',
            'HTTP request handling duration in seconds',
            ['method', 'route', 'status'],
            self::HTTP_BUCKETS,
        );
    }

    private function httpRequestMemoryBytes(): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'http_request_memory_bytes',
            'Peak memory used per HTTP request in bytes',
            ['method', 'route'],
            [1_048_576, 4_194_304, 8_388_608, 16_777_216, 33_554_432, 67_108_864, 134_217_728, 268_435_456],
        );
    }

    private function dbQueriesTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'db_queries_total',
            'Total database queries executed',
            ['connection'],
        );
    }

    private function dbQueryDurationSeconds(): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'db_query_duration_seconds',
            'Database query execution duration in seconds',
            ['connection'],
            self::QUERY_BUCKETS,
        );
    }

    private function cacheOperationsTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'cache_operations_total',
            'Cache operations by store and operation (hit, miss, write, forget)',
            ['store', 'operation'],
        );
    }

    private function mailSentTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'mail_sent_total',
            'Mail messages sent',
            ['transport'],
        );
    }

    private function notificationsSentTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'notifications_sent_total',
            'Notifications dispatched',
            ['channel'],
        );
    }

    private function exceptionsTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'exceptions_total',
            'Reported exceptions by class',
            ['class'],
        );
    }

    private function outgoingHttpRequestsTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'outgoing_http_requests_total',
            'Outgoing HTTP client requests',
            ['host', 'status'],
        );
    }

    private function outgoingHttpRequestDurationSeconds(): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'outgoing_http_request_duration_seconds',
            'Outgoing HTTP client request duration in seconds',
            ['host', 'status'],
            self::OUTGOING_HTTP_BUCKETS,
        );
    }

    private function jobsProcessedTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'jobs_processed_total',
            'Queue jobs processed by status (processed, failed)',
            ['connection', 'queue', 'class', 'status'],
        );
    }

    private function jobDurationSeconds(): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'job_duration_seconds',
            'Queue job processing duration in seconds',
            ['connection', 'queue', 'class'],
            self::JOB_BUCKETS,
        );
    }

    private function scheduledTasksTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'scheduled_tasks_total',
            'Scheduled task runs by status (finished, failed, skipped)',
            ['task', 'status'],
        );
    }

    private function scheduledTaskDurationSeconds(): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'scheduled_task_duration_seconds',
            'Scheduled task run duration in seconds',
            ['task'],
            self::JOB_BUCKETS,
        );
    }

    private function normalizeClass(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts) ?: $class;
    }
}
