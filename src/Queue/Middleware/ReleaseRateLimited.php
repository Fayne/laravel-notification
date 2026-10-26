<?php

namespace Fayne\LaravelNotification\Queue\Middleware;

use Fayne\LaravelNotification\Exceptions\RobotRateLimitedException;
use Illuminate\Queue\Jobs\SyncJob;

class ReleaseRateLimited
{
    public function handle(object $job, callable $next): mixed
    {
        try {
            return $next($job);
        } catch (RobotRateLimitedException $exception) {
            // A synchronous queue cannot retain a released job for later delivery.
            if (! $job->job || $job->job instanceof SyncJob) {
                throw $exception;
            }

            return $job->release($exception->retryAfter);
        }
    }
}
