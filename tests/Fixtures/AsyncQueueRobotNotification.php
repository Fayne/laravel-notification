<?php

namespace Fayne\LaravelNotification\Tests\Fixtures;

use Fayne\LaravelNotification\Queue\Middleware\ReleaseRateLimited;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AsyncQueueRobotNotification extends QueueRobotNotification implements ShouldQueue
{
    use Queueable;

    public function middleware(object $notifiable, string $channel): array
    {
        return [new ReleaseRateLimited];
    }
}
