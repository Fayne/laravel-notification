<?php

namespace Fayne\LaravelNotification\Tests\Feature;

use Fayne\LaravelNotification\Channels\DingdingChannel;
use Fayne\LaravelNotification\Channels\WecomChannel;
use Fayne\LaravelNotification\NotificationServiceProvider;
use Fayne\LaravelNotification\Tests\TestCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

class NotificationChannelsTest extends TestCase
{
    private array $responseBody = ['errcode' => 0, 'errmsg' => 'ok'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response($this->responseBody));
    }

    public function test_chained_anonymous_routes_deliver_both_messages_and_report_responses(): void
    {
        Event::fake([NotificationSent::class]);
        Notification::route('dingding', ['webhook' => 'https://oapi.dingtalk.com/robot/send?access_token=chosen', 'secret' => ''])
            ->route('wecom', 'chosen-key')
            ->notify(new RobotNotification('hello'));

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://oapi.dingtalk.com/robot/send?access_token=chosen' && $request['text']['content'] === 'hello');
        Http::assertSent(fn ($request) => $request->url() === 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=chosen-key' && $request['text']['content'] === 'hello');
        Event::assertDispatchedTimes(NotificationSent::class, 2);
        Event::assertDispatched(NotificationSent::class, fn ($event) => $event->response['errcode'] === 0);
    }

    public function test_native_messages_are_not_deduplicated_or_disabled_by_legacy_alarm_switches(): void
    {
        config(['dingding.enabled' => false, 'wechat.work.robot_alarm.enabled' => false]);
        $recipient = Notification::route('dingding', 'https://oapi.dingtalk.com/robot/send?access_token=chosen')->route('wecom', 'chosen');
        $recipient->notify(new RobotNotification('same'));
        $recipient->notify(new RobotNotification('same'));
        Http::assertSentCount(4);
    }

    public function test_model_routes_and_raw_message_arrays_work(): void
    {
        $message = ['msgtype' => 'markdown', 'markdown' => ['content' => '**hello**']];
        (new RobotUser)->notify(new RobotNotification($message));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->data() === $message && str_contains($request->url(), 'model-key'));
    }

    public function test_missing_routes_do_not_fall_back_to_global_robots(): void
    {
        Notification::route('mail', 'unused@example.test')->notify(new RobotNotification('hello'));
        Http::assertNothingSent();
    }

    public function test_via_selects_channels_instead_of_route_presence(): void
    {
        $notification = new RobotNotification('hello');
        $notification->channels = ['wecom'];
        Notification::route('dingding', 'https://oapi.dingtalk.com/robot/send?access_token=unused')->route('wecom', 'chosen')->notify($notification);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'chosen'));
    }

    public function test_queued_notifications_execute_without_creating_nested_jobs(): void
    {
        Queue::fake();
        Notification::route('dingding', 'https://oapi.dingtalk.com/robot/send?access_token=queue')->route('wecom', 'queue-key')->notify(new QueuedRobotNotification('queued'));
        Queue::assertPushed(SendQueuedNotifications::class, 2);
        Http::assertNothingSent();

        foreach (Queue::pushed(SendQueuedNotifications::class) as $entry) {
            $job = unserialize(serialize($entry));
            $job->handle(app(ChannelManager::class));
        }
        Http::assertSentCount(2);
        Queue::assertCount(2);
    }

    public function test_failure_does_not_dispatch_notification_sent(): void
    {
        $this->responseBody = ['errcode' => 1];
        Event::fake([NotificationSent::class]);
        $notification = new RobotNotification('hello');
        $notification->channels = ['wecom'];
        try {
            Notification::route('wecom', 'chosen')->notify($notification);
            $failed = false;
        } catch (\RuntimeException) {
            $failed = true;
        }
        $this->assertTrue($failed);
        Event::assertNotDispatched(NotificationSent::class);
    }

    public function test_package_defaults_merge_into_existing_nested_wechat_config(): void
    {
        config(['wechat' => ['work' => ['default' => ['corp_id' => 'host-corp']], 'custom' => 'keep']]);
        (new NotificationServiceProvider($this->app))->register();
        $this->assertSame('host-corp', config('wechat.work.default.corp_id'));
        $this->assertSame('keep', config('wechat.custom'));
        $this->assertSame(20, config('wechat.work.robot_alarm.count'));
    }

    public function test_channels_register_when_notification_manager_was_resolved_before_boot(): void
    {
        $manager = new ChannelManager($this->app);
        $this->app->instance(ChannelManager::class, $manager);
        (new NotificationServiceProvider($this->app))->boot();
        $this->assertInstanceOf(DingdingChannel::class, $manager->driver('dingding'));
        $this->assertInstanceOf(WecomChannel::class, $manager->driver('wecom'));
    }

    public function test_host_logging_configuration_is_not_overwritten(): void
    {
        $custom = ['driver' => 'single', 'path' => '/tmp/custom-robot.log'];
        config(['logging.channels.dingding' => $custom, 'logging.channels.wechat_robot' => $custom]);
        (new NotificationServiceProvider($this->app))->boot();
        $this->assertSame($custom, config('logging.channels.dingding'));
        $this->assertSame($custom, config('logging.channels.wechat_robot'));
    }

    public function test_disabled_alarm_channels_are_still_available_for_logging_stacks(): void
    {
        config(['dingding.enabled' => false, 'wechat.work.robot_alarm.enabled' => false]);
        config(['logging.channels.dingding' => null, 'logging.channels.wechat_robot' => null]);
        (new NotificationServiceProvider($this->app))->boot();
        $this->assertSame('custom', config('logging.channels.dingding.driver'));
        $this->assertSame('custom', config('logging.channels.wechat_robot.driver'));
    }
}

class RobotNotification extends BaseNotification
{
    public array $channels = ['dingding', 'wecom'];

    public function __construct(public string|array $message) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toDingding(object $notifiable): string|array
    {
        return $this->message;
    }

    public function toWecom(object $notifiable): string|array
    {
        return $this->message;
    }
}

class QueuedRobotNotification extends RobotNotification implements ShouldQueue
{
    use Queueable;
}

class RobotUser extends Model
{
    use Notifiable;

    public function routeNotificationForDingding(?BaseNotification $notification = null): string
    {
        return 'https://oapi.dingtalk.com/robot/send?access_token=model-token';
    }

    public function routeNotificationForWecom(?BaseNotification $notification = null): string
    {
        return 'model-key';
    }
}
