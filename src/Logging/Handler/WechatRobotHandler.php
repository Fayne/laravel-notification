<?php

namespace MobileNowGroup\LaravelNotification\Logging\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class WechatRobotHandler extends AbstractProcessingHandler
{
    /**
     * DingdingHandler constructor.
     *
     * @param  int  $level
     */
    public function __construct($level = Logger::ERROR, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * @throws \Exception
     */
    protected function write(array $record): void
    {
        app('app-chat-send')->send($record['message']);
    }
}
