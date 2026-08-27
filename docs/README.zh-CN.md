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
WECHAT_WORK_AGENT_CONTACTS_CORP_ID=
WECHAT_WORK_AGENT_CONTACTS_AGENT_ID=
WECHAT_WORK_AGENT_CONTACTS_SECRET=
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
DINGDING_SSL_VERIFY=true
DINGDING_LOGGING_CHANNEL=dingding
DINGDING_LOGGING_LEVEL=error
LOG_DINGDING_WEBHOOK_URL=
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

## 贡献指南

1. 创建功能分支
2. 完成功能改动
3. 为新增功能补充测试
4. 确保测试全部通过
5. 提交 Pull Request

## 许可证

本包采用 [专有许可证](../LICENSE.md)。
