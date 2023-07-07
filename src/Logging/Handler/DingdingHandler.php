<?php

namespace MobileNowGroup\LaravelNotification\Logging\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class DingdingHandler extends AbstractProcessingHandler
{
    /**
     * @var string
     */
    protected $keyword = '';

    /**
     * DingdingHandler constructor.
     *
     * @param  string  $keyword
     * @param  int  $level
     */
    public function __construct($keyword = '', $level = Logger::ERROR, bool $bubble = true)
    {
        $this->keyword = $keyword;

        parent::__construct($level, $bubble);
    }

    /**
     * @throws \Exception
     */
    protected function write(array $record): void
    {
        app('dingding')->send($record['message']);
    }
}
