<?php

namespace Fayne\LaravelNotification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Fayne\LaravelNotification\Services\AppChatSendService;

class SendWechatRobot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $params;

    public function __construct(array $params, int $delay = 0)
    {
        $this->params = $params;

        $this->delay(now()->addSecond($delay));
    }

    public function handle(AppChatSendService $service): void
    {
        $service->push($this->params);
    }
}
