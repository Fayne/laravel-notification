<?php

namespace MobileNowGroup\LaravelNotification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\LaravelNotification\Services\AppChatSendService;

class SendWechatRobot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array
     */
    protected $params;

    public function __construct(array $params, int $delay = 0)
    {
        $this->params = $params;

        $this->delay(now()->addSecond($delay));
    }

    public function tries(): int
    {
        return config('wechat.retry_times');
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
     */
    public function handle()
    {
        (new AppChatSendService())->push($this->params);
    }
}
