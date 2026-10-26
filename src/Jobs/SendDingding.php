<?php

namespace Fayne\LaravelNotification\Jobs;

use Fayne\LaravelNotification\Queue\Middleware\ReleaseRateLimited;
use Fayne\LaravelNotification\Services\SendDingdingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDingding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $params;

    public int $retryUntil;

    public int $maxExceptions;

    public function __construct(array $params)
    {
        $this->params = $params;
        $this->retryUntil = now()->addMinutes(10)->timestamp;
        $this->maxExceptions = max(1, (int) config('dingding.retry_times', 3));
    }

    public function middleware(): array
    {
        return [new ReleaseRateLimited];
    }

    public function backoff(): int
    {
        return 10;
    }

    public function handle(SendDingdingService $service): void
    {
        $service->push($this->params);
    }
}
