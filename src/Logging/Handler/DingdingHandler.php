<?php

namespace MobileNowGroup\LaravelNotification\Logging\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class DingdingHandler extends AbstractProcessingHandler
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
    protected function write($record): void
    {
        $record = is_object($record) ? $record->toArray() : $record;
        $context = ($record['context'] ?? []) ? sprintf("\nContext: %s", json_encode($record['context'])) : '';

        app('dingding')->send($record['message'] . $context);
    }
}
