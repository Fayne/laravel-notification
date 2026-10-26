<?php

namespace Fayne\LaravelNotification\Tests\Feature;

use Fayne\LaravelNotification\Integrations\SpatieBackup\BackupNotifiable;
use Fayne\LaravelNotification\Integrations\SpatieBackup\Notifications;
use Fayne\LaravelNotification\Tests\TestCase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Backup\BackupServiceProvider;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Events;
use Spatie\Backup\Notifications\EventHandler;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;

class SpatieBackupIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), BackupServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.name', 'Backup App');
        $app['config']->set('app.locale', 'en');
        $app['config']->set('backup.notifications.notifiable', BackupNotifiable::class);
        $app['config']->set('backup.notifications.dingding', ['webhook' => 'https://oapi.dingtalk.com/robot/send?access_token=backup', 'secret' => 'backup-secret']);
        $app['config']->set('backup.notifications.wecom.robot_key', 'backup-key');
        $notifications = [];
        foreach (self::events() as [$name]) {
            $notifications[Notifications::class.'\\'.$name.'Notification'] = ['dingding', 'wecom'];
        }
        $app['config']->set('backup.notifications.notifications', $notifications);
    }

    protected function setUp(): void
    {
        parent::setUp();
        EventHandler::enable();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['errcode' => 0])]);
    }

    public static function events(): array
    {
        return [
            'backup failure' => ['BackupHasFailed', 'Failed backup'],
            'backup success' => ['BackupWasSuccessful', 'Successful new backup'],
            'cleanup failure' => ['CleanupHasFailed', 'Cleaning up'],
            'cleanup success' => ['CleanupWasSuccessful', 'Clean up'],
            'unhealthy' => ['UnhealthyBackupWasFound', 'unhealthy'],
            'healthy' => ['HealthyBackupWasFound', 'healthy'],
        ];
    }

    #[DataProvider('events')]
    public function test_real_spatie_events_send_to_both_configured_robots(string $name, string $subject): void
    {
        Event::dispatch($this->backupEvent($name));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'access_token=backup') && str_contains($request->url(), 'sign='));
        Http::assertSent(fn ($request) => $request->url() === 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=backup-key');
        foreach (Http::recorded() as [$request]) {
            $content = $request['text']['content'];
            $this->assertStringContainsString($subject, $content);
            $this->assertStringContainsString('Backup App (testing)', $content);
            $this->assertStringContainsString('archive-disk', $content);
            $this->assertStringContainsString('nightly', $content);
            $this->assertStringNotContainsString('secret-database-password', $content);
        }
    }

    public function test_configured_channels_control_delivery_and_empty_lists_disable_events(): void
    {
        config(['backup.notifications.notifications.'.Notifications\BackupHasFailedNotification::class => ['wecom']]);
        Event::dispatch($this->backupEvent('BackupHasFailed'));
        Http::assertSentCount(1);
        config(['backup.notifications.notifications.'.Notifications\BackupHasFailedNotification::class => []]);
        app()->forgetInstance(Config::class);
        Event::dispatch($this->backupEvent('BackupHasFailed'));
        Http::assertSentCount(1);
    }

    public function test_missing_backup_routes_do_not_use_global_alarm_robots(): void
    {
        config(['backup.notifications.dingding' => [], 'backup.notifications.wecom' => []]);
        Event::dispatch($this->backupEvent('BackupHasFailed'));
        Http::assertNothingSent();
    }

    public function test_backup_notifiable_retains_mail_routing_and_does_not_inherit_global_signing_secret(): void
    {
        config(['backup.notifications.dingding.secret' => '', 'dingding.logging.secret' => 'unrelated-secret']);
        $recipient = new BackupNotifiable;
        $this->assertSame(config('backup.notifications.mail.to'), $recipient->routeNotificationForMail());
        Event::dispatch($this->backupEvent('BackupWasSuccessful'));
        Http::assertSent(fn ($request) => $request->url() === 'https://oapi.dingtalk.com/robot/send?access_token=backup');
    }

    public function test_notifications_retain_parent_mail_behavior_and_are_synchronous(): void
    {
        $notification = new Notifications\BackupHasFailedNotification(new Events\BackupHasFailed(new \Exception('local detail')));
        $this->assertInstanceOf(BackupHasFailedNotification::class, $notification);
        $this->assertInstanceOf(MailMessage::class, $notification->toMail());
        $this->assertNotInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_unhealthy_notification_identifies_checks_without_exposing_failure_messages(): void
    {
        Event::dispatch($this->backupEvent('UnhealthyBackupWasFound'));
        Http::assertSent(fn ($request) => str_contains($request['text']['content'], 'MaximumAgeInDays') && ! str_contains($request['text']['content'], 'secret-database-password'));
    }

    public function test_long_unicode_names_fit_robot_text_limits(): void
    {
        Event::dispatch(new Events\BackupWasSuccessful('archive-disk', str_repeat('备份', 1000)));
        foreach (Http::recorded() as [$request]) {
            $this->assertLessThanOrEqual(1900, strlen($request['text']['content']));
            $this->assertTrue(mb_check_encoding($request['text']['content'], 'UTF-8'));
        }
        Http::assertSentCount(2);
    }

    private function backupEvent(string $name): object
    {
        return match ($name) {
            'BackupHasFailed' => new Events\BackupHasFailed(new \Exception('secret-database-password'), 'archive-disk', 'nightly'),
            'CleanupHasFailed' => new Events\CleanupHasFailed(new \Exception('secret-database-password'), 'archive-disk', 'nightly'),
            'UnhealthyBackupWasFound' => new Events\UnhealthyBackupWasFound('archive-disk', 'nightly', collect([['check' => 'MaximumAgeInDays', 'message' => 'secret-database-password']])),
            default => new ('Spatie\\Backup\\Events\\'.$name)('archive-disk', 'nightly'),
        };
    }
}
