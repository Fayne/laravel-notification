<?php

namespace Fayne\LaravelNotification\Jobs;

use Fayne\LaravelNotification\Queue\Middleware\ReleaseRateLimited;
use Fayne\LaravelNotification\Services\AppChatSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWechatRobot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $params;

    public int $retryUntil;

    public int $maxExceptions;

    public function __construct(array $params, int $delay = 0)
    {
        $this->params = $params;
        $this->retryUntil = now()->addMinutes(10)->timestamp;
        $this->maxExceptions = max(1, (int) config('wechat.retry_times', 3));

        $this->delay(now()->addSecond($delay));
    }

    public function middleware(): array
    {
        return [new ReleaseRateLimited];
    }

    public function backoff(): int
    {
        return 10;
    }

    public function handle(AppChatSendService $service): void
    {
        $service->push($this->params);
    }
}
