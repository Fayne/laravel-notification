<?php

namespace MobileNowGroup\LaravelNotification\Tests;

use MobileNowGroup\LaravelNotification\NotificationServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [NotificationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('wechat.work.robot_alarm.enabled', true);
        $app['config']->set('wechat.work.robot_alarm.robot_key', 'robot-key');
        $app['config']->set('wechat.work.robot_alarm.level', 'error');
        $app['config']->set('dingding.enabled', true);
        $app['config']->set('dingding.logging.webhook', 'https://example.test/webhook');
        $app['config']->set('dingding.default_keyword', 'error');
        $app['config']->set('dingding.keywords.error', [
            'value' => 'error',
            'at_mobiles' => [],
        ]);
    }
}
