<?php

namespace MobileNowGroup\LaravelNotification\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WechatRobotNotificationSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var array
     */
    public $request;

    /**
     * @var array
     */
    public $response;

    /**
     * Create a new event instance.
     *
     * @param  array  $response
     */
    public function __construct(array $request, ?array $response = null)
    {
        $this->request = $request;
        $this->response = $response;
    }
}
