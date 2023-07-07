<?php

namespace MobileNowGroup\LaravelNotification\Logging;

use MobileNowGroup\LaravelNotification\Logging\Handler\DingdingHandler;
use Monolog\Logger;

class DingdingLogging
{
    /**
     * @return Logger
     */
    public function __invoke(array $config)
    {
        $logger = new Logger('dingding');
        $handler = new DingdingHandler($config['keyword'], $config['level']);
        $logger->pushHandler($handler);

        return $logger;
    }
}
