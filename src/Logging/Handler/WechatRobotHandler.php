<?php

namespace Fayne\LaravelNotification\Logging\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Logger;

class WechatRobotHandler extends AbstractProcessingHandler
{
    /**
     * DingdingHandler constructor.
     *
     * @param  int  $level
     */
    public function __construct(int|string $level = Logger::ERROR, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * @throws \Exception
     */
    protected function write(LogRecord $record): void
    {
        app('app-chat-send')->send($record->message);
    }
}
