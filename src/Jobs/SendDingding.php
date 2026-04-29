<?php

namespace MobileNowGroup\LaravelNotification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\LaravelNotification\Services\SendDingdingService;

class SendDingding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle(SendDingdingService $service): void
    {
        $service->push($this->params);
    }
}
