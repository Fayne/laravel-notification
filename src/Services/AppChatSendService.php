<?php

namespace Fayne\LaravelNotification\Services;

use Fayne\LaravelNotification\Jobs\SendWechatRobot;
use Fayne\LaravelNotification\Support\RobotDelivery;

class AppChatSendService
{
    public function send(string $content, ?array $mentionedMobileList = null, bool $lockContent = false): void
    {
        $params = $this->prepareParams($content, $mentionedMobileList, $lockContent);

        if (! $this->checkIsSend($params)) {
            return;
        }

        SendWechatRobot::dispatch($params);
    }

    public function push(array $params = [], bool $deduplicate = true): ?array
    {
        return (new RobotDelivery)->send('wecom', $this->webhook($params['robot_key']), $params['message'], $deduplicate);
    }

    public function checkIsDelay(): bool
    {
        return (new RobotDelivery)->retryAfter($this->webhook(config('wechat.work.robot_alarm.robot_key', ''))) > 0;
    }

    public function checkIsSend(array $params): bool
    {
        return config('wechat.work.robot_alarm.enabled') && ! empty($params['robot_key'])
            && ! (new RobotDelivery)->wasSent('wecom', $this->webhook($params['robot_key']), $params['message']);
    }

    public function setCache(array $params): void
    {
        (new RobotDelivery)->remember('wecom', $this->webhook($params['robot_key']), $params['message']);
    }

    private function webhook(string $route): string
    {
        return str_contains($route, '://') ? $route : 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key='.rawurlencode($route);
    }

    /**
     * @param  array|string[]  $mentionedMobileList
     */
    public function prepareParams(string $content, ?array $mentionedMobileList = null, bool $lockContent = false): array
    {
        $mentionedMobileList ??= config('wechat.work.robot_alarm.mentioned_mobile_list', ['@all']);
        $content = $lockContent ? $content : sprintf('[%s][%s]%s',
            config('app.name'),
            config('app.env'),
            $content
        );

        return [
            'robot_key' => config('wechat.work.robot_alarm.robot_key'),
            'message' => [
                'msgtype' => 'text',
                'text' => [
                    'content' => $content,
                    'mentioned_mobile_list' => $mentionedMobileList,
                ],
            ],
        ];
    }
}
