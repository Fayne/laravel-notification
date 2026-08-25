<?php

namespace Fayne\LaravelNotification\Logging;

use Fayne\LaravelNotification\Logging\Handler\WechatRobotHandler;
use Monolog\Logger;

class WechatRobotLogging
{
    /**
     * @return Logger
     */
    public function __invoke(array $config)
    {
        $logger = new Logger('wechat_robot');

        $handler = new WechatRobotHandler($config['level']);
        $logger->pushHandler($handler);

        return $logger;
    }
}
