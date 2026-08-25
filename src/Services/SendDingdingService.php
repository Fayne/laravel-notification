<?php

namespace Fayne\LaravelNotification\Services;

use Illuminate\Support\Facades\Http;
use Fayne\LaravelNotification\Jobs\SendDingding;

class SendDingdingService
{
    /**
     * 发送文本类型的消息
     *
     * @param $content string 消息内容
     * @param  string  $keyword 关键词
     * @param  bool  $isAtAll 是否 @ 所有人
     * @param  bool|null  $lockContent 是否需要拼接content
     *
     */
    public function send(string $content, string $keyword = 'error', bool $isAtAll = false, bool $lockContent = false): void
    {
        $params = $this->prepareParams($content, $keyword, $isAtAll, $lockContent);

        if (! $this->checkIsSend($params)) {
            return;
        }

        $this->setCache($params);
        SendDingding::dispatch($params);
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    private function prepareParams(string $content, string $keyword = 'error', bool $isAtAll = false, bool $lockContent = false): array
    {
        $keywordInfo = config('dingding.keywords')[$keyword] ?? [];

        if (empty($keywordInfo)) {
            throw new \Exception('关键词与钉钉机器人不对应，请至钉钉群检查！');
        }

        $requestId = config('dingding.only_request_id') ? (request()->header(config('dingding.request_id_key')) ?? $content) : $content;

        $content = $lockContent ? $content : sprintf('[%s][%s][%s][%s%s]%s%s',
            config('app.name'),
            config('app.env'),
            $keyword,
            $requestId ? 'request id : ' : '',
            $requestId,
            'error message : ',
            $content
        );

        return [
            'msgtype' => 'text',
            'text' => [
                'content' => $content,
            ],
            'at' => [
                'atMobiles' => $keywordInfo['at_mobiles'] ?? [], //被艾特的手机号
                'isAtAll' => $isAtAll, //是否全员艾特
            ],
            'keyword' => $keyword,
        ];
    }

    private function checkIsSend(array $params): bool
    {
        if (! config('dingding.enabled') || ! config('dingding.logging.webhook')) {
            return false;
        }

        if (cache()->tags('dingding')->get(md5($params['text']['content']))) {
            return false;
        }

        return true;
    }

    public function setCache(array $params): void
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
    public function push(array $params = []): ?array
    {
        return Http::post(config('dingding.logging.webhook'), $params)->json();
    }
}
