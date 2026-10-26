<?php

namespace Fayne\LaravelNotification\Tests\Feature;

use Fayne\LaravelNotification\Exceptions\RobotRateLimitedException;
use Fayne\LaravelNotification\Services\AppChatSendService;
use Fayne\LaravelNotification\Services\SendDingdingService;
use Fayne\LaravelNotification\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class RobotDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['dingding.logging.webhook' => 'https://oapi.dingtalk.com/robot/send?access_token=test']);
    }

    public static function failures(): array
    {
        return [
            'business error' => [['errcode' => 310000, 'errmsg' => 'rejected'], 200],
            'server error' => [['errcode' => 0], 500],
            'missing status' => [['unexpected' => true], 200],
            'invalid JSON' => ['not json', 200],
        ];
    }

    #[DataProvider('failures')]
    public function test_dingding_failure_throws_without_exposing_webhook(array|string $body, int $status): void
    {
        Http::fake(['*' => Http::response($body, $status)]);
        try {
            (new SendDingdingService)->push(['msgtype' => 'text', 'text' => ['content' => 'test']]);
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('access_token', $exception->getMessage());

            return;
        }
        $this->fail('Delivery failure must throw.');
    }

    public function test_wecom_uses_message_robot_key_without_enterprise_credentials(): void
    {
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        (new AppChatSendService)->push(['robot_key' => 'chosen-key', 'message' => ['msgtype' => 'text', 'text' => ['content' => 'test']]]);
        Http::assertSent(fn ($request) => $request->url() === 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=chosen-key');
    }

    public function test_dingding_signs_chosen_robot_and_does_not_send_keyword_metadata(): void
    {
        $this->freezeTime();
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        $route = ['webhook' => 'https://oapi.dingtalk.com/robot/send?access_token=other', 'secret' => 'chosen-secret'];
        (new SendDingdingService)->push(['msgtype' => 'text', 'text' => ['content' => 'test'], 'keyword' => 'error'], $route, false);
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            $timestamp = now()->getTimestampMs();
            $sign = base64_encode(hash_hmac('sha256', $timestamp."\nchosen-secret", 'chosen-secret', true));

            return $query['access_token'] === 'other' && $query['timestamp'] === (string) $timestamp
                && $query['sign'] === $sign && ! isset($request->data()['keyword']);
        });
    }

    public function test_legacy_deduplication_works_with_file_cache_and_expires(): void
    {
        $path = sys_get_temp_dir().'/notification-test-'.bin2hex(random_bytes(8));
        config(['cache.default' => 'file', 'cache.stores.file.path' => $path, 'cache.stores.file.lock_path' => $path]);
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        try {
            $service = new SendDingdingService;
            $message = ['msgtype' => 'text', 'text' => ['content' => 'test']];
            $service->push($message);
            $service->push($message);
            Http::assertSentCount(1);
            $this->travel(61)->seconds();
            $service->push($message);
            Http::assertSentCount(2);
        } finally {
            app('files')->deleteDirectory($path);
        }
    }

    public function test_failed_delivery_can_be_retried_and_deduplication_is_per_robot_and_payload(): void
    {
        Http::fake(['*' => Http::sequence()->push(['errcode' => 1])->push(['errcode' => 0])->push(['errcode' => 0])->push(['errcode' => 0])->whenEmpty(Http::response(['errcode' => 0]))]);
        $service = new SendDingdingService;
        $message = ['msgtype' => 'text', 'text' => ['content' => 'same']];
        try {
            $service->push($message);
        } catch (RuntimeException) {
        }
        $service->push($message);
        $service->push($message);
        $service->push($message, ['webhook' => 'https://oapi.dingtalk.com/robot/send?access_token=other', 'secret' => '']);
        $service->push($message + ['at' => ['isAtAll' => true]]);
        Http::assertSentCount(4);
    }

    public function test_wecom_rate_window_is_per_robot_and_does_not_slide(): void
    {
        $this->freezeTime();
        config(['wechat.work.robot_alarm.count' => 2]);
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        $service = new AppChatSendService;
        $params = ['robot_key' => 'limited', 'message' => ['msgtype' => 'text', 'text' => ['content' => 'same']]];
        $service->push($params, false);
        $this->travel(30)->seconds();
        $service->push($params, false);
        $service->push(array_replace($params, ['robot_key' => 'other']), false);
        try {
            $service->push($params, false);
            $this->fail('Rate limit must be explicit.');
        } catch (RobotRateLimitedException $exception) {
            $this->assertSame(30, $exception->retryAfter);
        }
        $this->travel(31)->seconds();
        $service->push($params, false);
        Http::assertSentCount(4);
    }

    public function test_transport_has_bounded_timeouts(): void
    {
        config(['dingding.http.timeout' => 8, 'dingding.http.connect_timeout' => 2]);
        Http::fake(function ($request, $options) {
            $this->assertSame(8, $options['timeout']);
            $this->assertSame(2, $options['connect_timeout']);

            return Http::response(['errcode' => 0]);
        });
        (new SendDingdingService)->push(['msgtype' => 'text', 'text' => ['content' => 'test']]);
    }
}
