<?php

namespace Fayne\LaravelNotification\Tests\Unit;

use Fayne\LaravelNotification\Logging\Handler\DingdingHandler;
use Fayne\LaravelNotification\Logging\Handler\WechatRobotHandler;
use Fayne\LaravelNotification\Tests\TestCase;
use Mockery;
use Monolog\Level;
use Monolog\Logger;

class MonologHandlerCompatibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dingding_handler_accepts_logrecord(): void
    {
        $dingding = Mockery::mock();
        $dingding->shouldReceive('send')->once()->with('monolog-message', 'error');
        $this->app->instance('dingding', $dingding);

        $handler = new DingdingHandler('error', Logger::ERROR);
        $logger = new Logger('test');
        $logger->pushHandler($handler);
        $logger->addRecord(Level::Error, 'monolog-message');
        $this->assertTrue(true);
    }

    public function test_wechat_handler_accepts_logrecord(): void
    {
        $wechat = Mockery::mock();
        $wechat->shouldReceive('send')->once()->with('wechat-message');
        $this->app->instance('app-chat-send', $wechat);

        $handler = new WechatRobotHandler(Logger::ERROR);
        $logger = new Logger('test');
        $logger->pushHandler($handler);
        $logger->error('wechat-message');
        $this->assertTrue(true);
    }
}
