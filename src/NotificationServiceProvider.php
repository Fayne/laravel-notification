<?php

namespace Fayne\LaravelNotification;

use Fayne\LaravelNotification\Channels\DingdingChannel;
use Fayne\LaravelNotification\Channels\WecomChannel;
use Fayne\LaravelNotification\Logging\DingdingLogging;
use Fayne\LaravelNotification\Logging\WechatRobotLogging;
use Fayne\LaravelNotification\Services\AppChatSendService;
use Fayne\LaravelNotification\Services\SendDingdingService;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootWechat();
        $this->bootDingDing();

        $this->callAfterResolving(ChannelManager::class, function (ChannelManager $manager) {
            $manager->extend('dingding', fn ($app) => $app->make(DingdingChannel::class));
            $manager->extend('wecom', fn ($app) => $app->make(WecomChannel::class));
        });
    }

    private function bootWechat()
    {
        $loggingChannel = config('wechat.work.robot_alarm.logging.channel', 'wechat_robot');
        if (config('logging.channels.'.$loggingChannel) !== null) {
            return;
        }

        $this->app->make('config')->set('logging.channels.'.$loggingChannel, [
            'driver' => 'custom',
            'via' => WechatRobotLogging::class,
            'level' => config('wechat.work.robot_alarm.logging.level'),
        ]);
    }

    private function bootDingDing()
    {
        $loggingChannel = config('dingding.logging.channel', 'dingding');
        if (config('logging.channels.'.$loggingChannel) !== null) {
            return;
        }

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
            return new AppChatSendService;
        });

        $this->app->singleton('dingding', function () {
            return new SendDingdingService;
        });

        return $this;
    }

    private function mergeDefaults(array $defaults, array $configured): array
    {
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $configured)) {
                $configured[$key] = $value;
            } elseif (is_array($value) && ! array_is_list($value) && is_array($configured[$key])) {
                $configured[$key] = $this->mergeDefaults($value, $configured[$key]);
            }
        }

        return $configured;
    }

    private function setupConfig(): void
    {
        if (! $this->app->configurationIsCached()) {
            foreach (['wechat', 'dingding'] as $key) {
                $defaults = require dirname(__DIR__).'/config/'.$key.'.php';
                $this->app['config']->set($key, $this->mergeDefaults($defaults, config($key, [])));
            }
        }

        $this->publishes([
            dirname(__DIR__).'/config/wechat.php' => config_path('wechat.php'),
            dirname(__DIR__).'/config/dingding.php' => config_path('dingding.php'),
        ], 'config');
    }
}
