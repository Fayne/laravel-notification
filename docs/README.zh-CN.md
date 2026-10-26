# Laravel Notification（中文文档）

[English](../README.md)

用于企业微信机器人和钉钉机器人的通知包。

## 版本兼容

- Laravel: `^13.0`
- PHP: `^8.3`

当前主版本仅支持 Laravel 13。

## 安装

```bash
composer require fayne/laravel-notification
php artisan vendor:publish --provider="Fayne\LaravelNotification\NotificationServiceProvider"
```

如果你使用 `stack` 日志通道，请在 `config/logging.php` 中加入：

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'daily', 'dingding', 'wechat_robot'],
        'ignore_exceptions' => false,
    ],
],
```

## 企业微信机器人

### 环境变量

```dotenv
WECHAT_ROBOT_ENABLE=true
WECHAT_WORK_AGENT_CONTACTS_ROBOT_KEY=
WECHAT_WORK_AGENT_ROBOT_MENTIONED_MOBILE_LIST=@all
WECHAT_ROBOT_LOGGING_CHANNEL=wechat_robot
WECHAT_ROBOT_LEVEL=error
```

### 直接发送

```php
app('app-chat-send')->send('测试企业微信机器人消息');
```

## 钉钉机器人

### 环境变量

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

### 可选：请求上下文

你可以在应用的异常处理器中补充上下文信息：

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

并在全局中间件中加入 request ID 中间件：

```php
\Fayne\LaravelNotification\Http\Middleware\RequestId::class,
```

### 直接发送

```php
app('dingding')->send('测试钉钉机器人消息');
```

## Laravel 原生通知

包注册了 `dingding`、`wecom` 两个通知通道。这里的 `wecom` 是**企业微信群机器人**，不包含企业应用向成员或部门发消息；群机器人不需要企业 ID、应用 secret 或企业 access token。

在应用中创建通知类：

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

通过你需要的链式调用向两个机器人发消息：

```php
use App\Notifications\AppSystemNotification;
use Illuminate\Support\Facades\Notification;

Notification::route('dingding', [
    'webhook' => config('services.dingding.webhook'),
    'secret' => config('services.dingding.secret'),
])->route('wecom', config('services.wecom.robot_key'))
    ->notify(new AppSystemNotification('error：系统通知内容'));
```

示例中的 `services.*` 需要自行添加到应用的 `config/services.php`，包不会自动创建这些配置。也可以在 `route()` 中显式使用包已有的 `dingding.logging.webhook`、`dingding.logging.secret`、`wechat.work.robot_alarm.robot_key`。

- 钉钉地址支持完整 HTTPS Webhook 字符串，或 `['webhook' => '...', 'secret' => '...']`。字符串以及未提供 secret 的数组使用全局 `dingding.logging.secret`；显式传入 `secret => ''` 表示该机器人不加签。如果机器人使用关键词校验，消息中仍需包含对应关键词。
- 企业微信地址支持机器人 key 或完整 HTTPS Webhook。
- `via()` 决定发送哪些通道；`route()` 提供接收地址。缺失的地址会跳过，不会回退到全局机器人。
- 显式调用原生通道不受旧日志告警开关控制；消息不会自动添加应用/环境前缀，也不会按内容去重。
- 两个 `to*()` 方法都可以返回机器人原生消息数组，例如 `['msgtype' => 'markdown', 'markdown' => ['content' => '**通知**']]`；具体字段需符合对应平台的消息格式。
- Webhook 地址应来自可信配置。发送不跟随重定向，始终验证 TLS 证书；旧 `curl_verify` 配置保留，但不能关闭证书验证。

在使用 Laravel `Notifiable` trait 的模型中，也可以添加路由方法：

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

随后调用 `$user->notify(new AppSystemNotification('error：系统通知内容'));` 即可。

## 队列通知与限流

原生通道在 Laravel 通知任务内部直接发送，不再创建第二层任务。需要异步投递、并在限流时延后重试时，可继承上面的通知类：

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

在相同的 `notify()` 调用中改用 `new QueuedAppSystemNotification(...)`，并配置异步队列、启动 worker。中间件会按照限流剩余时间重新释放任务；不添加中间件时，原生通道会抛出 `RobotRateLimitedException`，由 Laravel 普通异常重试规则处理。同步调用（包括 `sync` 队列）会明确抛出限流异常，不会静默丢弃消息。Laravel 的 `retryUntil()` 优先于尝试次数限制；示例允许在十分钟内因限流重新入队，并通过 `maxExceptions` 限制真正的投递错误。

企业微信按机器人使用固定 60 秒窗口的共享缓存额度，原生通道和旧入口共用；`wechat.work.robot_alarm.count` 默认为 20。窗口不会在每次发送后顺延，任务重新执行时会再次检查额度。失败的出站请求也消耗额度。其他应用若使用同一个机器人，必须共享缓存和此实现才能统一计数。

旧 `send()` 入口继续使用队列，按机器人和完整消息保留 60 秒去重，仅在发送成功后记录；已入队的重复消息也会在执行时再次检查。旧 `queue_delay` 配置保留，实际延迟改为额度窗口的剩余时间。旧任务使用固定十分钟截止时间、十秒错误重试间隔，真正的异常次数由 `dingding.retry_times` / `wechat.retry_times` 控制，默认 3 次。

## 部署和验证

- 生产环境使用支持原子锁的共享持久缓存，例如 Redis 或数据库缓存。文件缓存适合单机；内存数组缓存无法协调多个进程。多 worker、多服务器需要共享缓存。
- HTTP 默认总超时 10 秒、连接超时 3 秒，通过 `dingding.http.*`、`wechat.work.robot_alarm.http.*` 配置。worker 超时应大于 HTTP 超时，队列 `retry_after` 应大于 worker 超时。
- HTTP 失败、非零 `errcode`、非法响应均抛出 `RobotDeliveryException`，发送失败不会被当作 `NotificationSent`。超时也可能发生在平台已接收之后，因此重试无法保证接收端严格只收到一次。
- 日志 Handler 入队失败不会破坏其他日志处理，会向 PHP 错误日志写入不含密钥的诊断。机器人投递异常不会再次触发机器人日志告警，避免异步循环；应保留本地日志并监控失败任务。
- 包保留宿主已有日志通道和嵌套微信配置。发布配置时请合并已有文件，避免覆盖其他微信集成。
- 上线前更新宿主应用的锁定依赖并执行 `composer audit --no-dev`；本包本地忽略的 vendor/lock 不会自动更新宿主应用。更新代码或配置后重启 worker。生产上线前，需要用自己的机器人验证钉钉加签/关键词、企业微信收取、worker 重试和失败处理，以及共享缓存限流。

运行 `composer test` 执行自动化测试。当前实现已在 PHP 8.3 / 8.4、Laravel 13.7.0 / 13.38.0 下验证，包括使用模拟 HTTP 响应的真实 SQLite 数据库队列执行流程；这不能替代真实平台投递、Redis 或生产环境验收。

## 贡献指南

1. 创建功能分支
2. 完成功能改动
3. 为新增功能补充测试
4. 确保测试全部通过
5. 提交 Pull Request

## 许可证

本包采用 [专有许可证](../LICENSE.md)。
