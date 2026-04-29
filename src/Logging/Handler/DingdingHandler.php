<?php

namespace MobileNowGroup\LaravelNotification\Logging\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Logger;

class DingdingHandler extends AbstractProcessingHandler
{
    protected string $keyword = '';

    /**
     * DingdingHandler constructor.
     *
     * @param  string  $keyword
     * @param  int  $level
     */
    public function __construct(string $keyword = '', int|string $level = Logger::ERROR, bool $bubble = true)
    {
        $this->keyword = $keyword;

        parent::__construct($level, $bubble);
    }

    /**
     * @throws \Exception
     */
    protected function write(LogRecord $record): void
    {
        app('dingding')->send($record->message, $this->keyword ?: 'error');
    }
}
