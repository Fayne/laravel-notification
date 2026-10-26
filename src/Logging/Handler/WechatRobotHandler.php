<?php

namespace Fayne\LaravelNotification\Logging\Handler;

use Fayne\LaravelNotification\Exceptions\RobotDeliveryException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;

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

    protected function write(LogRecord $record): void
    {
        if (($record->context['exception'] ?? null) instanceof RobotDeliveryException) {
            return;
        }

        try {
            app('app-chat-send')->send($record->message);
        } catch (\Throwable $exception) {
            error_log('WeCom robot log delivery failed ('.get_class($exception).').');
        }
    }
}
