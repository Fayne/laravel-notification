<?php

namespace Fayne\LaravelNotification\Tests\Feature;

use Fayne\LaravelNotification\Exceptions\RobotDeliveryException;
use Fayne\LaravelNotification\Exceptions\RobotRateLimitedException;
use Fayne\LaravelNotification\NotificationServiceProvider;
use Fayne\LaravelNotification\Services\AppChatSendService;
use Fayne\LaravelNotification\Services\SendDingdingService;
use Fayne\LaravelNotification\Tests\TestCase;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class DeliveryEdgeCasesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['dingding.logging.webhook' => 'https://oapi.dingtalk.com/robot/send?access_token=test']);
    }

    #[DataProvider('failures')]
    public function test_wecom_rejects_http_business_and_malformed_responses(array|string $body, int $status): void
    {
        Http::fake(['*' => Http::response($body, $status)]);
        $this->expectException(RuntimeException::class);
        (new AppChatSendService)->push(['robot_key' => 'key', 'message' => ['msgtype' => 'text', 'text' => ['content' => 'test']]]);
    }

    public static function failures(): array
    {
        return RobotDeliveryTest::failures();
    }

    public function test_deduplication_lock_blocks_concurrent_send_then_allows_failed_message_to_retry(): void
    {
        $service = new SendDingdingService;
        $params = ['msgtype' => 'text', 'text' => ['content' => 'test']];
        $requests = 0;
        Http::fake(function () use ($service, $params, &$requests) {
            $requests++;
            if ($requests === 1) {
                try {
                    $service->push($params);
                    $this->fail('Concurrent delivery must be deferred.');
                } catch (RobotRateLimitedException $exception) {
                    $this->assertSame(1, $exception->retryAfter);
                }

                return Http::response(['errcode' => 1]);
            }

            return Http::response(['errcode' => 0]);
        });
        try {
            $service->push($params);
        } catch (RuntimeException) {
        }
        $service->push($params);
        $service->push($params);
        $this->assertSame(2, $requests);
    }

    public function test_connection_failure_does_not_poison_deduplication_or_leak_url(): void
    {
        $requests = 0;
        Http::fake(function () use (&$requests) {
            if (++$requests === 1) {
                return Http::failedConnection('secret access_token=test');
            }

            return Http::response(['errcode' => 0]);
        });
        $service = new SendDingdingService;
        $params = ['msgtype' => 'text', 'text' => ['content' => 'test']];
        try {
            $service->push($params);
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('secret', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $service->push($params);
        $this->assertSame(2, $requests);
    }

    public function test_partial_http_transfer_failure_is_sanitized_into_a_robot_exception(): void
    {
        Http::fake(function () {
            throw RequestException::create(
                new Request('POST', 'https://oapi.dingtalk.com/robot/send?access_token=secret'),
                new Response(500, [], 'sensitive-provider-response')
            );
        });
        $caught = null;
        try {
            (new SendDingdingService)->push(['msgtype' => 'text', 'text' => ['content' => 'test']]);
        } catch (\Throwable $exception) {
            $caught = $exception;
        }
        $this->assertInstanceOf(RobotDeliveryException::class, $caught);
        $this->assertStringNotContainsString('sensitive-provider-response', $caught->getMessage());
        $this->assertStringNotContainsString('access_token', $caught->getMessage());
        $this->assertNull($caught->getPrevious());
    }

    public function test_https_redirects_are_not_followed_and_certificate_verification_is_enabled(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify']);

            return Http::response('', 302, ['Location' => 'https://example.test/collect']);
        });
        $this->expectException(RuntimeException::class);
        (new SendDingdingService)->push(['msgtype' => 'text', 'text' => ['content' => 'test']]);
    }

    public function test_disabled_legacy_sender_skips_keyword_validation_and_dispatch(): void
    {
        Bus::fake();
        config(['dingding.enabled' => false, 'dingding.keywords' => []]);
        (new SendDingdingService)->send('ignored');
        Bus::assertNothingDispatched();
    }

    public function test_legacy_wecom_respects_configured_mentions_and_explicit_empty_mentions(): void
    {
        config(['wechat.work.robot_alarm.mentioned_mobile_list' => ['123']]);
        $service = new AppChatSendService;
        $this->assertSame(['123'], $service->prepareParams('test')['message']['text']['mentioned_mobile_list']);
        $this->assertSame([], $service->prepareParams('test', [])['message']['text']['mentioned_mobile_list']);
    }

    public function test_nested_config_merge_respects_explicit_empty_recipient_lists(): void
    {
        config(['wechat.work.robot_alarm.mentioned_mobile_list' => []]);
        (new NotificationServiceProvider($this->app))->register();
        $this->assertSame([], config('wechat.work.robot_alarm.mentioned_mobile_list'));
    }

    public function test_wecom_key_and_full_webhook_share_the_same_rate_limit(): void
    {
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        config(['wechat.work.robot_alarm.count' => 1]);
        $service = new AppChatSendService;
        $params = ['robot_key' => 'shared-key', 'message' => ['msgtype' => 'text', 'text' => ['content' => 'test']]];
        $service->push($params, false);
        $params['robot_key'] = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=shared-key';
        $this->expectException(RobotRateLimitedException::class);
        $service->push($params, false);
    }

    public function test_string_dingding_route_uses_global_secret_but_explicit_empty_secret_disables_signing(): void
    {
        config(['dingding.logging.secret' => 'global-secret']);
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        $service = new SendDingdingService;
        $message = ['msgtype' => 'text', 'text' => ['content' => 'test']];
        $url = 'https://oapi.dingtalk.com/robot/send?access_token=test';
        $service->push($message, $url, false);
        $service->push($message, ['webhook' => $url, 'secret' => ''], false);
        $urls = Http::recorded()->map(fn ($pair) => $pair[0]->url());
        $this->assertStringContainsString('&sign=', $urls[0]);
        $this->assertSame($url, $urls[1]);
    }
}
