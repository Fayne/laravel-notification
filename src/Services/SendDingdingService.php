<?php

namespace MobileNowGroup\LaravelNotification\Services;

use Illuminate\Support\Facades\Http;
use MobileNowGroup\LaravelNotification\Jobs\SendDingding;

class SendDingdingService
{
    /**
     * 发送文本类型的消息
     *
     * @param $content string 消息内容
     * @param  bool  $isAtAll 是否 @ 所有人
     * @param  bool|null  $lockContent 是否需要拼接content
     *
     * @throws \Exception
     */
    public function send(string $content, bool $isAtAll = false, ?bool $lockContent = false)
    {
        try {
            $params = $this->prepareParams($content, $isAtAll, $lockContent);

            if (! $this->checkIsSend($params)) {
                return;
            }

            $this->setCache($params);
            SendDingding::dispatch($params);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    private function prepareParams(string $content, bool $isAtAll = false, ?bool $lockContent = false)
    {
        $requestId = config('dingding.only_request_id') ? (request()->header(config('dingding.request_id_key')) ?? $content) : $content;

        $content = $lockContent ? $content : sprintf('[%s][%s][%s%s]%s',
            config('app.name'),
            config('app.env'),
            $requestId ? 'request id : ' : '',
            $requestId,
            $content
        );

        return [
            'msgtype' => 'text',
            'text' => [
                'content' => $content,
            ],
            'at' => [
                'atMobiles' => config('dingding.at_mobiles') ?? [], //被艾特的手机号
                'isAtAll' => $isAtAll, //是否全员艾特
            ]
        ];
    }

    private function checkIsSend($params)
    {
        if (!config('dingding.enabled') || !config('dingding.logging.webhook')) {
            return false;
        }

        if (cache()->tags('dingding')->get(md5($params['text']['content']))) {
            return false;
        }

        return true;
    }

    public function setCache($params)
    {
        cache()->tags('dingding')->put(
            md5($params['text']['content']),
            $params['at'],
            now()->addMinutes(1)
        );
    }

    /**
     * 发送
     *
     * @param  array  $params 请求需要的参数
     */
    public function push(array $params = [])
    {
        return Http::post($this->getHttpUrl(), $params)->json();
    }

    /**
     * @return string
     */
    public function getHttpUrl(): string
    {
        $ding_webhook = config('dingding.logging.webhook');
        $timestamp = time() * 1000;
        $sdata = $timestamp . "\n" . config('dingding.logging.secret');
        $signStr = base64_encode(hash_hmac('sha256', $sdata, config('dingding.logging.secret'),true));
        $signStr = utf8_encode(urlencode($signStr));

        return "{$ding_webhook}&timestamp={$timestamp}&sign={$signStr}";
    }
}
