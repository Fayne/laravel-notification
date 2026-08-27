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
WECHAT_WORK_AGENT_CONTACTS_CORP_ID=
WECHAT_WORK_AGENT_CONTACTS_AGENT_ID=
WECHAT_WORK_AGENT_CONTACTS_SECRET=
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
DINGDING_SSL_VERIFY=true
DINGDING_LOGGING_CHANNEL=dingding
DINGDING_LOGGING_LEVEL=error
LOG_DINGDING_WEBHOOK_URL=
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

## Contributing

1. Create a feature branch
2. Make your changes
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

This package is software licensed under the [Proprietary license](LICENSE.md).
