<?php

namespace Fayne\LaravelNotification\Logging\Handler;

use Fayne\LaravelNotification\Exceptions\RobotDeliveryException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;

class DingdingHandler extends AbstractProcessingHandler
{
    protected string $keyword = '';

    /**
     * DingdingHandler constructor.
     *
     * @param  int  $level
     */
    public function __construct(string $keyword = '', int|string $level = Logger::ERROR, bool $bubble = true)
    {
        $this->keyword = $keyword;

        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (($record->context['exception'] ?? null) instanceof RobotDeliveryException) {
            return;
        }

        try {
            app('dingding')->send($record->message, $this->keyword ?: 'error');
        } catch (\Throwable $exception) {
            error_log('DingTalk robot log delivery failed ('.get_class($exception).').');
        }
    }
}
