<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Notification

[简体中文](docs/README.zh-CN.md)

Notification package for WeChat Work Robot and DingTalk Robot.

## Version Compatibility

- Laravel: `^13.0`
- PHP: `^8.3`

This major version targets Laravel 13 only.

## Installation

```bash
composer require fayne/laravel-notification
php artisan vendor:publish --provider="Fayne\LaravelNotification\NotificationServiceProvider"
```

Add channels to `config/logging.php` if you are using `stack`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'daily', 'dingding', 'wechat_robot'],
        'ignore_exceptions' => false,
    ],
],
```

## WeChat Work Robot

### Environment

```dotenv
WECHAT_ROBOT_ENABLE=true
WECHAT_WORK_AGENT_CONTACTS_ROBOT_KEY=
WECHAT_WORK_AGENT_ROBOT_MENTIONED_MOBILE_LIST=@all
WECHAT_ROBOT_LOGGING_CHANNEL=wechat_robot
WECHAT_ROBOT_LEVEL=error
```

### Direct Send

```php
app('app-chat-send')->send('Test WeChat Work robot message');
```

## DingTalk Robot

### Environment

```dotenv
DINGDING_ENABLED=true
DINGDING_ONLY_REQUEST_ID=true
DINGDING_ONLY_REQUEST_KEY=fayne-request-id
DINGDING_LOGGING_CHANNEL=dingding
DINGDING_LOGGING_LEVEL=error
LOG_DINGDING_WEBHOOK_URL=
DINGDING_SECRET=
DINGDING_CHANNEL_KEYWORD=error
DINGDING_ERROR_MOBILES=
DINGDING_ALARM_MOBILES=
```

### Optional Request Context

You can enrich exception context in your app's exception handler:

```php
protected function context(): array
{
    try {
        return array_filter([
            'userId' => auth()->id(),
            'requestId' => request()->header(config('dingding.request_id_key')),
            'requestParameters' => request()->input(),
        ]);
    } catch (\Throwable $e) {
        return [];
    }
}
```

And add request ID middleware to your global middleware stack:

```php
\Fayne\LaravelNotification\Http\Middleware\RequestId::class,
```

### Direct Send

```php
app('dingding')->send('Test DingTalk robot message');
```

## Native Laravel Notifications

The package registers `dingding` and `wecom` as Laravel notification channels. `wecom` means a **WeChat Work group robot**, not enterprise application messages to members or departments. Group robots do not need a corporate ID or enterprise access token.

Create an application notification:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AppSystemNotification extends Notification
{
    public function __construct(public string $content) {}

    public function via(object $notifiable): array
    {
        return ['dingding', 'wecom'];
    }

    public function toDingding(object $notifiable): string|array
    {
        return $this->content;
    }

    public function toWecom(object $notifiable): string|array
    {
        return $this->content;
    }
}
```

Send to two robots:

```php
use App\Notifications\AppSystemNotification;
use Illuminate\Support\Facades\Notification;

Notification::route('dingding', [
    'webhook' => config('services.dingding.webhook'),
    'secret' => config('services.dingding.secret'),
])->route('wecom', config('services.wecom.robot_key'))
    ->notify(new AppSystemNotification('error: a system notification'));
```

Define those application-specific `services.*` values in `config/services.php`; the package does not create them. Alternatively, use the package's existing `dingding.logging.webhook`, `dingding.logging.secret` and `wechat.work.robot_alarm.robot_key` values explicitly in `route()`.

- DingTalk accepts an HTTPS webhook string or `['webhook' => '...', 'secret' => '...']`. A string or an omitted secret uses `dingding.logging.secret`; `secret => ''` disables signing for that route. A keyword-secured robot still requires its keyword in your message.
- WeCom accepts a robot key or a complete HTTPS webhook URL.
- `via()` selects the channels. `route()` supplies each recipient; missing routes are skipped, with no fallback to a global robot.
- Explicit native notifications work independently of legacy alarm enable switches. They send the exact message without adding the application/environment prefix and without content deduplication.
- Both `to*()` methods may return a native robot payload array, for example `['msgtype' => 'markdown', 'markdown' => ['content' => '**hello**']]`. Use each platform's own supported payload shape.
- Only use trusted configuration for webhook routes. Redirects are disabled and TLS verification is always enabled. The legacy `curl_verify` setting is retained for compatibility but does not disable verification.

For a model using Laravel's `Notifiable` trait, add routing methods:

```php
public function routeNotificationForDingding($notification): array
{
    return [
        'webhook' => config('services.dingding.webhook'),
        'secret' => config('services.dingding.secret'),
    ];
}

public function routeNotificationForWecom($notification): string
{
    return config('services.wecom.robot_key');
}
```

Then call `$user->notify(new AppSystemNotification('error: a system notification'));`.

## Queued Notifications and Rate Limits

Native channels send within Laravel's notification job; they do not create nested jobs. To send asynchronously and release rate-limited deliveries, extend the example above:

```php
<?php

namespace App\Notifications;

use DateTimeInterface;
use Fayne\LaravelNotification\Queue\Middleware\ReleaseRateLimited;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedAppSystemNotification extends AppSystemNotification implements ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 3;

    public function middleware(object $notifiable, string $channel): array
    {
        return [new ReleaseRateLimited];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    public function backoff(): int
    {
        return 10;
    }
}
```

Use `new QueuedAppSystemNotification(...)` in the same `notify()` call. Configure an asynchronous queue and run a worker. The middleware releases throttled jobs after the remaining wait; without it a native channel throws `RobotRateLimitedException`, and normal Laravel exception retry rules apply. Synchronous calls, including the `sync` queue, throw on throttling rather than silently dropping messages. Laravel's `retryUntil()` takes priority over attempt limits; the example allows releases for ten minutes while `maxExceptions` bounds delivery errors.

WeCom reserves a per-robot quota in a fixed 60-second window, shared by native and legacy sends; `wechat.work.robot_alarm.count` defaults to 20. The window does not slide on each send. Throttling is checked again after every release. Failed outbound requests also consume quota. This local limiter cannot account for other applications using the same robot unless they share this cache and implementation.

The legacy `send()` methods remain queued and keep 60-second deduplication per robot and complete payload, recorded only after successful delivery. Duplicate jobs already queued are checked again by the worker. `queue_delay` remains in the configuration for compatibility; actual queue release now uses the remaining quota window. Legacy jobs have a fixed ten-minute deadline, a ten-second error backoff, and an exception budget from `dingding.retry_times` / `wechat.retry_times` (default 3).

## Deployment and Verification

- Use a shared, persistent Laravel cache supporting atomic locks, such as Redis or database. File cache works on a single host; an in-memory array cache cannot coordinate processes. Multiple workers/hosts must share the cache.
- HTTP defaults: 10-second total timeout and 3-second connect timeout. Configure `dingding.http.*` and `wechat.work.robot_alarm.http.*`. Keep worker timeout above HTTP timeout and queue `retry_after` above worker timeout.
- HTTP failures, nonzero `errcode` and malformed responses throw `RobotDeliveryException`. A transport failure is not reported as `NotificationSent`. A timeout can still occur after the provider accepted a request; delivery retries cannot guarantee exactly-once receipt.
- Robot log-handler enqueue failures are isolated and emit a secret-free PHP error-log diagnostic. Robot delivery exceptions are not fed back into robot log channels, preventing asynchronous alert loops; keep a local log channel and monitor failed jobs.
- Host-defined log channels and nested WeChat configuration are preserved. When publishing configuration, merge changes into existing files instead of overwriting another WeChat integration.
- Update the consuming application’s locked dependencies and run `composer audit --no-dev` before deploying; this package’s ignored local vendor/lock are not shipped to your application. Restart workers after updating the package/configuration. Verify actual DingTalk signing/keywords, WeCom receipt, worker retry/failure handling and shared-cache throttling with your own robots before production rollout.

Run `composer test` for automated verification. The implementation has been tested with PHP 8.3 / 8.4 and Laravel 13.7.0 / 13.38.0, including an actual SQLite-backed queue worker flow with stubbed HTTP responses. This does not establish real provider delivery, Redis behavior or production readiness for your deployment.

## Optional Spatie Backup Integration

Projects using `spatie/laravel-backup:^10.0` can reuse six notification classes and `BackupNotifiable` included in this package. Backup is an optional runtime integration; it is required only for development tests and is not installed transitively for ordinary consumers.

Install Backup in the application if needed:

```bash
composer require spatie/laravel-backup:^10.0
```

In the application's existing `config/backup.php`, **replace** the original notification-class entries with the corresponding classes below. Keep the other Backup configuration and existing mail/slack/etc. settings. Do not retain both the original Spatie class and our class for the same event: Spatie selects the first matching class basename.

```php
use Fayne\LaravelNotification\Integrations\SpatieBackup\BackupNotifiable;
use Fayne\LaravelNotification\Integrations\SpatieBackup\Notifications as RobotBackup;

// Inside config/backup.php's returned array:
'notifications' => [
    'notifications' => [
        RobotBackup\BackupHasFailedNotification::class => ['mail', 'dingding', 'wecom'],
        RobotBackup\CleanupHasFailedNotification::class => ['mail', 'dingding', 'wecom'],
        RobotBackup\UnhealthyBackupWasFoundNotification::class => ['mail', 'dingding', 'wecom'],
        RobotBackup\BackupWasSuccessfulNotification::class => ['wecom'],
        RobotBackup\CleanupWasSuccessfulNotification::class => [],
        RobotBackup\HealthyBackupWasFoundNotification::class => [],
    ],

    'notifiable' => BackupNotifiable::class,

    'dingding' => [
        'webhook' => env('BACKUP_DINGDING_WEBHOOK', ''),
        'secret' => env('BACKUP_DINGDING_SECRET', ''),
    ],

    'wecom' => [
        'robot_key' => env('BACKUP_WECOM_ROBOT_KEY', ''),
    ],

    // Keep the existing mail, slack, discord and webhook configuration here.
],
```

Example environment variables:

```dotenv
BACKUP_DINGDING_WEBHOOK=https://oapi.dingtalk.com/robot/send?access_token=...
BACKUP_DINGDING_SECRET=
BACKUP_WECOM_ROBOT_KEY=
```

- The example sends failures/unhealthy reports to mail and both robots, successful backups to WeCom, and disables cleanup-success/healthy reports. Change the channel lists per project; an empty list disables that event's notifications. If using `mail`, configure Spatie's existing mail recipient and Laravel mail transport.
- `BackupNotifiable` inherits Spatie's mail/other routing and explicitly reads `backup.notifications.dingding.*` and `backup.notifications.wecom.robot_key` through `config()`. Missing robot addresses skip that channel. Neither addresses nor signing secrets fall back to global log-alarm robots; robot enable switches do not control these explicit notifications. WeCom also accepts a full HTTPS webhook in `robot_key`.
- All six classes inherit the matching Spatie notification, retain its `via()`/mail/other message methods, and add `toDingding()`/`toWecom()`. No extra provider, event listener or manual `Notification::route()` call is needed.
- Robot text includes Spatie's localized subject (application/environment), backup name and disk. Failure/unhealthy text has an `error` marker; other text has an `info` marker. Health failures identify check names. Exception messages, stack traces and raw health-check failure text are omitted; inspect application logs for full diagnostics. Text is limited to 1,900 UTF-8 bytes. A keyword-secured DingTalk robot must accept the generated message; signing is recommended when failures and successes share a robot.
- Delivery is synchronous by default, avoiding an additional queue dependency for backup alerts. HTTP/limit failures propagate; the integration does not silently claim success or persist retries. If you need async delivery, subclass the relevant integration notification under the **same short class name**, implement `ShouldQueue`, and apply the middleware/deadline/exception-budget pattern in the queued-notification section. Change the mapping to your subclasses.
- After changing config, rebuild cached configuration and restart long-running workers as applicable. Verify real robot receipt in staging. Automated integration tests exercise all six actual Spatie events through its event handler and Laravel channels with fake HTTP; they do not execute a real backup or prove provider delivery.

## Contributing

1. Create a feature branch
2. Make your changes
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

This package is software licensed under the [Proprietary license](LICENSE.md).
