<?php

namespace Fayne\LaravelNotification;

use Illuminate\Support\ServiceProvider;
use Fayne\LaravelNotification\Logging\DingdingLogging;
use Fayne\LaravelNotification\Logging\WechatRobotLogging;
use Fayne\LaravelNotification\Services\AppChatSendService;
use Fayne\LaravelNotification\Services\SendDingdingService;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (config('wechat.work.robot_alarm.enabled')) {
            $this->bootWechat();
        }

        if (config('dingding.enabled')) {
            $this->bootDingDing();
        }
    }

    private function bootWechat()
    {
        $loggingChannel = config('wechat.work.robot_alarm.logging.channel', 'wechat_robot');
        $this->app->make('config')->set('logging.channels.'.$loggingChannel, [
            'driver' => 'custom',
            'via' => WechatRobotLogging::class,
            'level' => config('wechat.work.robot_alarm.logging.level'),
        ]);
    }

    private function bootDingDing()
    {
        $loggingChannel = config('dingding.logging.channel', 'dingding');
        $this->app->make('config')->set('logging.channels.'.$loggingChannel, [
            'driver' => 'custom',
            'via' => DingdingLogging::class,
            'keyword' => config('dingding.default_keyword'),
            'level' => config('dingding.logging.level'),
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->bindingServices()
            ->setupConfig();
    }

    /**
     * @return $this
     */
    private function bindingServices(): self
    {
        $this->app->singleton('app-chat-send', function ($app) {
            return new AppChatSendService();
        });

        $this->app->singleton('dingding', function () {
            return new SendDingdingService();
        });

        return $this;
    }

    private function setupConfig(): void
    {
        if (! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom(dirname(__DIR__).'/config/wechat.php', 'wechat');
            $this->mergeConfigFrom(dirname(__DIR__).'/config/dingding.php', 'dingding');
        }

        $this->publishes([
            dirname(__DIR__).'/config/wechat.php' => config_path('wechat.php'),
            dirname(__DIR__).'/config/dingding.php' => config_path('dingding.php'),
        ], 'config');
    }
}
