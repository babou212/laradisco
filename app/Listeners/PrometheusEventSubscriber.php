<?php

namespace App\Listeners;

use App\Support\Metrics\MetricsRecorder;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Throwable;

class PrometheusEventSubscriber
{
    /** @var array<string, float> */
    private array $jobStartedAt = [];

    public function __construct(private readonly MetricsRecorder $recorder) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            QueryExecuted::class => 'handleQueryExecuted',
            CacheHit::class => 'handleCacheHit',
            CacheMissed::class => 'handleCacheMissed',
            KeyWritten::class => 'handleKeyWritten',
            KeyForgotten::class => 'handleKeyForgotten',
            MessageSent::class => 'handleMessageSent',
            NotificationSent::class => 'handleNotificationSent',
            ResponseReceived::class => 'handleHttpResponseReceived',
            ConnectionFailed::class => 'handleHttpConnectionFailed',
            JobProcessing::class => 'handleJobProcessing',
            JobProcessed::class => 'handleJobProcessed',
            JobFailed::class => 'handleJobFailed',
            ScheduledTaskFinished::class => 'handleScheduledTaskFinished',
            ScheduledTaskFailed::class => 'handleScheduledTaskFailed',
            ScheduledTaskSkipped::class => 'handleScheduledTaskSkipped',
        ];
    }

    public function handleQueryExecuted(QueryExecuted $event): void
    {
        $this->safe(fn () => $this->recorder->recordQuery(
            connection: $event->connectionName,
            durationSeconds: ($event->time ?? 0) / 1000,
        ));
    }

    public function handleCacheHit(CacheHit $event): void
    {
        $this->safe(fn () => $this->recorder->recordCacheOperation($event->storeName ?? 'default', 'hit'));
    }

    public function handleCacheMissed(CacheMissed $event): void
    {
        $this->safe(fn () => $this->recorder->recordCacheOperation($event->storeName ?? 'default', 'miss'));
    }

    public function handleKeyWritten(KeyWritten $event): void
    {
        $this->safe(fn () => $this->recorder->recordCacheOperation($event->storeName ?? 'default', 'write'));
    }

    public function handleKeyForgotten(KeyForgotten $event): void
    {
        $this->safe(fn () => $this->recorder->recordCacheOperation($event->storeName ?? 'default', 'forget'));
    }

    public function handleMessageSent(MessageSent $event): void
    {
        $this->safe(fn () => $this->recorder->recordMail($event->data['__transport'] ?? 'default'));
    }

    public function handleNotificationSent(NotificationSent $event): void
    {
        $this->safe(fn () => $this->recorder->recordNotification($event->channel));
    }

    public function handleHttpResponseReceived(ResponseReceived $event): void
    {
        $this->safe(function () use ($event) {
            $request = $event->request;
            $response = $event->response;

            $this->recorder->recordOutgoingHttp(
                host: parse_url($request->url(), PHP_URL_HOST) ?: 'unknown',
                status: $response->status(),
                durationSeconds: ($response->handlerStats()['total_time'] ?? 0.0),
            );
        });
    }

    public function handleHttpConnectionFailed(ConnectionFailed $event): void
    {
        $this->safe(fn () => $this->recorder->recordOutgoingHttp(
            host: parse_url($event->request->url(), PHP_URL_HOST) ?: 'unknown',
            status: 0,
            durationSeconds: 0.0,
        ));
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        $this->jobStartedAt[$event->job->getJobId()] = microtime(true);
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $this->safe(function () use ($event) {
            $id = $event->job->getJobId();
            $duration = isset($this->jobStartedAt[$id]) ? microtime(true) - $this->jobStartedAt[$id] : 0.0;
            unset($this->jobStartedAt[$id]);

            $this->recorder->recordJob(
                connection: $event->connectionName,
                queue: $event->job->getQueue() ?? 'default',
                jobClass: $event->job->resolveName(),
                status: 'processed',
                durationSeconds: $duration,
            );
        });
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $this->safe(function () use ($event) {
            $id = $event->job->getJobId();
            $duration = isset($this->jobStartedAt[$id]) ? microtime(true) - $this->jobStartedAt[$id] : 0.0;
            unset($this->jobStartedAt[$id]);

            $this->recorder->recordJob(
                connection: $event->connectionName,
                queue: $event->job->getQueue() ?? 'default',
                jobClass: $event->job->resolveName(),
                status: 'failed',
                durationSeconds: $duration,
            );
        });
    }

    public function handleScheduledTaskFinished(ScheduledTaskFinished $event): void
    {
        $this->safe(fn () => $this->recorder->recordScheduledTask(
            task: $this->scheduledTaskName($event->task),
            status: 'finished',
            durationSeconds: $event->runtime ?? 0.0,
        ));
    }

    public function handleScheduledTaskFailed(ScheduledTaskFailed $event): void
    {
        $this->safe(fn () => $this->recorder->recordScheduledTask(
            task: $this->scheduledTaskName($event->task),
            status: 'failed',
            durationSeconds: 0.0,
        ));
    }

    public function handleScheduledTaskSkipped(ScheduledTaskSkipped $event): void
    {
        $this->safe(fn () => $this->recorder->recordScheduledTask(
            task: $this->scheduledTaskName($event->task),
            status: 'skipped',
            durationSeconds: 0.0,
        ));
    }

    private function scheduledTaskName(object $task): string
    {
        if (method_exists($task, 'description') && $task->description) {
            return (string) $task->description;
        }

        return property_exists($task, 'command') && is_string($task->command)
            ? $task->command
            : ($task->getSummaryForDisplay ?? $task::class);
    }

    private function safe(\Closure $work): void
    {
        try {
            $work();
        } catch (Throwable) {
        }
    }
}
