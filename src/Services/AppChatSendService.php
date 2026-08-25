<?php

namespace Fayne\LaravelNotification\Services;

use GuzzleHttp\Exception\GuzzleException;
use Fayne\LaravelNotification\Jobs\SendWechatRobot;
use Overtrue\LaravelWeChat\Facade as EasyWeChat;

class AppChatSendService
{
    public function send(string $content, array $mentionedMobileList = ['@all'], bool $lockContent = false): void
    {
        $params = $this->prepareParams($content, $mentionedMobileList, $lockContent);

        if (! $this->checkIsSend($params)) {
            return;
        }

        $this->setCache($params);
        $delay = $this->checkIsDelay() ? (int) config('wechat.work.robot_alarm.queue_delay') : 0;
        SendWechatRobot::dispatch($params, $delay);
    }

    /**
     * 发送
     *
     * @param  array  $params 请求需要的参数
     *
     * @throws \Exception|GuzzleException
     */
    public function push(array $params = []): void
    {
        EasyWeChat::work()->group_robot->send($params['robot_key'], $params['message']);
    }

    public function checkIsDelay(): bool
    {
        $count = cache()->tags('wechat_robot')->get('wechat_robot_count');
        if ($count > config('wechat.work.robot_alarm.count')) {
            return true;
        }

        return false;
    }

    public function checkIsSend(array $params): bool
    {
        if (! config('wechat.work.robot_alarm.enabled')) {
            return false;
        }

        if (cache()->tags('wechat_robot')->get(md5($params['message']['text']['content']))) {
            return false;
        }

        return true;
    }

    public function setCache(array $params): void
    {
        $count = (cache()->tags('wechat_robot')->get('wechat_robot_count') ?? 0) + 1;
        cache()->tags('wechat_robot')->put(
            'wechat_robot_count',
            $count,
            now()->addMinutes(1)
        );
        cache()->tags('wechat_robot')->put(
            md5($params['message']['text']['content']),
            $params['robot_key'],
            now()->addMinutes(1)
        );
    }

    /**
     * @param  array|string[]  $mentionedMobileList
     * @return array
     */
    public function prepareParams(string $content, array $mentionedMobileList = ['@all'], bool $lockContent = false): array
    {
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
