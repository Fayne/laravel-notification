<?php

namespace Fayne\LaravelNotification\Tests\Feature;

use Fayne\LaravelNotification\Exceptions\RobotDeliveryException;
use Fayne\LaravelNotification\Exceptions\RobotRateLimitedException;
use Fayne\LaravelNotification\Jobs\SendDingding;
use Fayne\LaravelNotification\Jobs\SendWechatRobot;
use Fayne\LaravelNotification\Logging\Handler\DingdingHandler;
use Fayne\LaravelNotification\Logging\Handler\WechatRobotHandler;
use Fayne\LaravelNotification\Services\AppChatSendService;
use Fayne\LaravelNotification\Services\SendDingdingService;
use Fayne\LaravelNotification\Tests\Fixtures\AsyncQueueRobotNotification;
use Fayne\LaravelNotification\Tests\Fixtures\QueueRobotNotification;
use Fayne\LaravelNotification\Tests\TestCase;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class QueueAndLoggingTest extends TestCase
{
    public static function channels(): array
    {
        return ['dingding' => ['dingding'], 'wecom' => ['wecom']];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_legacy_wecom_job_releases_until_the_actual_window_resets(): void
    {
        $this->freezeTime();
        config(['wechat.work.robot_alarm.count' => 1]);
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        $service = new AppChatSendService;
        $service->push(['robot_key' => 'limited', 'message' => ['msgtype' => 'text', 'text' => ['content' => 'first']]]);
        $this->travel(20)->seconds();
        $job = (new SendWechatRobot(['robot_key' => 'limited', 'message' => ['msgtype' => 'text', 'text' => ['content' => 'second']]]))->withFakeQueueInteractions();
        $this->runQueued($job, fn ($job) => $job->handle($service));
        $job->assertReleased(40);
        Http::assertSentCount(1);
        $this->travel(41)->seconds();
        $job->withFakeQueueInteractions();
        $this->runQueued($job, fn ($job) => $job->handle($service));
        $job->assertNotReleased();
        Http::assertSentCount(2);
    }

    public function test_native_queue_middleware_releases_throttled_notifications(): void
    {
        config(['wechat.work.robot_alarm.count' => 1]);
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        Notification::route('wecom', 'limited')->notify(new QueueRobotNotification('first'));
        Queue::fake();
        Notification::route('wecom', 'limited')->notify(new AsyncQueueRobotNotification('second'));
        $job = unserialize(serialize(Queue::pushed(SendQueuedNotifications::class)->first()));
        $job->withFakeQueueInteractions();
        $this->runQueued($job, fn ($job) => $job->handle(app(ChannelManager::class)));
        $job->assertReleased();
        Http::assertSentCount(1);
    }

    public function test_synchronous_delivery_reports_throttling_instead_of_swallowing_it(): void
    {
        config(['queue.default' => 'sync', 'wechat.work.robot_alarm.count' => 1]);
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        $service = new AppChatSendService;
        $service->push(['robot_key' => 'robot-key', 'message' => ['msgtype' => 'text', 'text' => ['content' => 'first']]]);
        $this->expectException(RobotRateLimitedException::class);
        $service->send('second');
    }

    #[DataProvider('channels')]
    public function test_queue_has_fixed_deadline_and_bounded_exception_retries(string $channel): void
    {
        $this->freezeTime();
        config(['dingding.retry_times' => 4, 'wechat.retry_times' => 4]);
        $job = $channel === 'dingding' ? new SendDingding([]) : new SendWechatRobot([]);
        $deadline = now()->addMinutes(10)->timestamp;
        $job = unserialize(serialize($job));
        $this->travel(5)->minutes();
        $this->assertSame($deadline, $job->retryUntil);
        $this->assertSame(4, $job->maxExceptions);
        $this->assertSame(10, $job->backoff());
    }

    #[DataProvider('channels')]
    public function test_queue_dispatch_failure_does_not_poison_deduplication(string $channel): void
    {
        $bus = Mockery::mock(QueueingDispatcher::class);
        $bus->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
        $this->app->instance(Dispatcher::class, $bus);
        $service = $channel === 'dingding' ? new SendDingdingService : new AppChatSendService;
        try {
            $service->send('same');
        } catch (RuntimeException) {
        }
        Bus::fake();
        $service->send('same');
        Bus::assertDispatched($channel === 'dingding' ? SendDingding::class : SendWechatRobot::class);
    }

    #[DataProvider('channels')]
    public function test_log_delivery_failure_does_not_break_other_handlers_or_leak_secrets(string $channel): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notification-log-');
        $previous = ini_set('error_log', $path);
        try {
            $service = Mockery::mock();
            $service->shouldReceive('send')->once()->andThrow(new RuntimeException('secret-webhook-url'));
            $this->app->instance($channel === 'dingding' ? 'dingding' : 'app-chat-send', $service);
            $local = new TestHandler;
            $robot = $channel === 'dingding' ? new DingdingHandler : new WechatRobotHandler;
            $logger = new Logger('test', [$robot, $local]);
            $logger->error('business failure');
            $this->assertTrue($local->hasError('business failure'));
            $diagnostic = file_get_contents($path);
            $this->assertStringContainsString('robot log delivery failed', $diagnostic);
            $this->assertStringNotContainsString('secret-webhook-url', $diagnostic);
        } finally {
            ini_set('error_log', $previous);
            unlink($path);
        }
    }

    #[DataProvider('channels')]
    public function test_robot_job_failure_is_not_sent_back_to_the_robot_log_channel(string $channel): void
    {
        $service = Mockery::mock();
        $service->shouldNotReceive('send');
        $this->app->instance($channel === 'dingding' ? 'dingding' : 'app-chat-send', $service);
        $local = new TestHandler;
        $robot = $channel === 'dingding' ? new DingdingHandler : new WechatRobotHandler;
        $logger = new Logger('test', [$robot, $local]);
        $logger->error('queue delivery failed', ['exception' => new RobotDeliveryException('robot unavailable')]);
        $this->assertTrue($local->hasError('queue delivery failed'));
    }

    private function runQueued(object $job, callable $next): void
    {
        $middleware = array_merge(method_exists($job, 'middleware') ? $job->middleware() : [], $job->middleware ?? []);
        (new Pipeline($this->app))->send($job)->through($middleware)->then($next);
    }
}
