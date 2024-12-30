<p align="center"><img src="http://res.cloudinary.com/guorenjun/image/upload/v1531809978/MN_LOGO_3.png" width="300"></p>

# Laravel Notification

### 环境要求



### 安装

```shell
composer config repositories.mobilenowgroup/laravel-notification git git@github.com:MobileNowGroup/laravel-notification.git

composer require mobilenowgroup/laravel-notification:^11.0

php artisan vendor:publish --provider="MobileNowGroup\LaravelNotification\NotificationServiceProvider"
```

把下配置到config/logging.php
```php
'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'daily', 'dingding', 'wechat_robot'],
            'ignore_exceptions' => false,
        ],
]
```

## 1.企业微信通知

### 使用之前的准备工作

1. 创建企业微信群群
2. 群右上角三个点->添加群机器人【一定要记录下来Webhook链接中的key配置到.env `WECHAT_WORK_AGENT_CONTACTS_ROBOT_KEY` 或config/wechat.php中】
3. 获取企业微信corpid、agentid、secret:https://developer.work.weixin.qq.com/document/path/90665


### 配置(.env)
把下面配置复制到.env文件中

如果想要关闭企业微信通知，将WECHAT_ROBOT_ENABLE设置为false
```
WECHAT_ROBOT_ENABLE=true
WECHAT_WORK_AGENT_CONTACTS_CORP_ID=
WECHAT_WORK_AGENT_CONTACTS_SECRET=
WECHAT_WORK_AGENT_CONTACTS_ROBOT_KEY=
WECHAT_WORK_AGENT_ROBOT_MENTIONED_MOBILE_LIST=告警提示人
WECHAT_ROBOT_LOGGING_CHANNEL=wechat_robot
WECHAT_ROBOT_LEVEL=error
```

### 两种使用方式

#### Laravel自带的系统报错日志
默认channel名称`wecaht_robot` 已经在package里定义，无需更改。如果一定要更改，可修改.env配置文件中的`WECHAT_ROBOT_LOGGING_CHANNEL`值

#### 直接发送企业微信消息

```shell
app('app-chat-send')->send('测试企业微信机器人！');
```

## 2.钉钉通知

### 使用之前的准备工作

1. 创建钉钉群
2. 设置->智能助手->增加钉钉机器人【一定要记录下来Webhook配置到.env `LOG_DINGDING_WEBHOOK_URL` 或config/dingding.php中】
3. 选中加签方式进行发送机器人【一定要记录下来secret到配置.env`DINGDING_SECRET`或config/dingding.php中】

### 配置(.env)
把下面配置复制到.env文件中
如果想要关闭企业微信通知，DINGDING_ENABLED设置为false
```
DINGDING_ENABLED=true
DINGDING_ONLY_REQUEST_ID=true
DINGDING_ONLY_REQUEST_KEY=mn-request-id
DINGDING_SSL_VERIFY=true
DINGDING_LOGGING_CHANNEL=dingding
DINGDING_LOGGING_LEVEL=level
LOG_DINGDING_WEBHOOK_URL=
DINGDING_SECRET=
DINGDING_ERROR_MOBILES=错误提示人
```

在 __app/Exceptions/Handler.php__ 文件中修改继承的 __context__ 方法
```php
<?php
// ...
/**
 * Get the default context variables for logging.
 *
 * @return array
 */
protected function context()
{
    try {
        return array_filter([
            'userId' => auth()->id(),
            'requestId' => request()->header(config('dingding.request_id_key')),
            'requestParameters' => request()->input(),
            // 'email' => optional(Auth::user())->email,
        ]);
    } catch (Throwable $e) {
        return [];
    }
}

```

在 __app/Http/Kernel.php__ 中添加全局middleware
```php
<?php
// ...
/**
 * The application's global HTTP middleware stack.
 *
 * These middleware are run during every request to your application.
 *
 * @var array
 */
protected $middleware = [
    // ...,
    \MobileNowGroup\LaravelNotification\Http\Middleware\RequestId::class,
];
```

### 两种使用方式

#### Laravel自带的系统报错日志
默认channel名称`dingding` 已经在package里定义，无需更改。如果一定要更改，可修改.env配置文件中的`DINGDING_LOGGING_CHANNEL`值

#### 直接发送钉钉消息

```shell
app('dingding')->send('测试钉钉机器人！');
```



