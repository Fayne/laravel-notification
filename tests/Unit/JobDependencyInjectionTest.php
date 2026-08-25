<?php

namespace Fayne\LaravelNotification\Tests\Unit;

use Fayne\LaravelNotification\Jobs\SendDingding;
use Fayne\LaravelNotification\Jobs\SendWechatRobot;
use Fayne\LaravelNotification\Services\AppChatSendService;
use Fayne\LaravelNotification\Services\SendDingdingService;
use Fayne\LaravelNotification\Tests\TestCase;
use Mockery;

class JobDependencyInjectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_wechat_robot_job_uses_injected_service(): void
    {
        $service = Mockery::mock(AppChatSendService::class);
        $service->shouldReceive('push')->once()->with(['foo' => 'bar']);

        $job = new SendWechatRobot(['foo' => 'bar']);
        $job->handle($service);
        $this->assertTrue(true);
    }

    public function test_send_dingding_job_uses_injected_service(): void
    {
        $service = Mockery::mock(SendDingdingService::class);
        $service->shouldReceive('push')->once()->with(['foo' => 'bar']);

        $job = new SendDingding(['foo' => 'bar']);
        $job->handle($service);
        $this->assertTrue(true);
    }
}
