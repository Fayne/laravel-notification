<?php

namespace Fayne\LaravelNotification\Support;

use Fayne\LaravelNotification\Exceptions\RobotDeliveryException;
use Fayne\LaravelNotification\Exceptions\RobotRateLimitedException;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class RobotDelivery
{
    public function key(string $channel, string $webhook, array $message): string
    {
        return 'robot-notification:sent:'.hash('sha256', $channel.$webhook.json_encode($message, JSON_THROW_ON_ERROR));
    }

    public function wasSent(string $channel, string $webhook, array $message): bool
    {
        return cache()->has($this->key($channel, $webhook, $message));
    }

    public function remember(string $channel, string $webhook, array $message): void
    {
        cache()->put($this->key($channel, $webhook, $message), true, 60);
    }

    public function retryAfter(string $webhook): int
    {
        $state = cache()->get($this->rateKey($webhook));

        return $state && $state['count'] >= (int) config('wechat.work.robot_alarm.count', 20)
            ? max(0, $state['reset_at'] - now()->timestamp) : 0;
    }

    public function send(string $channel, string $webhook, array $message, bool $deduplicate = false, ?string $secret = null): ?array
    {
        if (! filter_var($webhook, FILTER_VALIDATE_URL) || parse_url($webhook, PHP_URL_SCHEME) !== 'https'
            || parse_url($webhook, PHP_URL_USER) !== null || parse_url($webhook, PHP_URL_FRAGMENT) !== null) {
            throw new RobotDeliveryException('A valid HTTPS robot webhook is required.');
        }

        if ($deduplicate && $this->wasSent($channel, $webhook, $message)) {
            return null;
        }

        $config = $channel === 'wecom' ? 'wechat.work.robot_alarm' : 'dingding';
        $timeout = (int) config($config.'.http.timeout', 10);
        $sentKey = $this->key($channel, $webhook, $message);
        $lock = $deduplicate ? cache()->lock($sentKey.':lock', $timeout + 10) : null;

        if ($lock && ! $lock->get()) {
            throw new RobotRateLimitedException(1);
        }

        try {
            if ($deduplicate && $this->wasSent($channel, $webhook, $message)) {
                return null;
            }

            if ($channel === 'wecom') {
                $this->reserve($webhook);
            }

            if ($secret !== null && $secret !== '') {
                $timestamp = now()->getTimestampMs();
                $sign = base64_encode(hash_hmac('sha256', $timestamp."\n".$secret, $secret, true));
                $webhook = (string) Uri::withQueryValues(new Uri($webhook), [
                    'timestamp' => (string) $timestamp, 'sign' => $sign,
                ]);
            }

            try {
                $response = Http::connectTimeout((int) config($config.'.http.connect_timeout', 3))
                    ->timeout($timeout)
                    ->withOptions(['allow_redirects' => false, 'verify' => true])
                    ->post($webhook, $message);
            } catch (ConnectionException|RequestException) {
                throw new RobotDeliveryException($channel.' robot connection failed.');
            }

            if (! $response->successful()) {
                throw new RobotDeliveryException($channel.' robot returned HTTP '.$response->status().'.');
            }

            $body = $response->json();
            if (! is_array($body) || ! array_key_exists('errcode', $body) || $body['errcode'] !== 0) {
                throw new RobotDeliveryException($channel.' robot rejected the message or returned an invalid response.');
            }

            if ($deduplicate) {
                cache()->put($sentKey, true, 60);
            }

            return $body;
        } finally {
            $lock?->release();
        }
    }

    private function rateKey(string $webhook): string
    {
        return 'robot-notification:rate:'.hash('sha256', $webhook);
    }

    private function reserve(string $webhook): void
    {
        $key = $this->rateKey($webhook);
        $lock = cache()->lock($key.':lock', 5);
        if (! $lock->get()) {
            throw new RobotRateLimitedException(1);
        }

        try {
            $now = now()->timestamp;
            $state = cache()->get($key, ['count' => 0, 'reset_at' => $now + 60]);
            if ($state['reset_at'] <= $now) {
                $state = ['count' => 0, 'reset_at' => $now + 60];
            }
            if ($state['count'] >= max(1, (int) config('wechat.work.robot_alarm.count', 20))) {
                throw new RobotRateLimitedException(max(1, $state['reset_at'] - $now));
            }
            $state['count']++;
            cache()->put($key, $state, $state['reset_at'] - $now);
        } finally {
            $lock->release();
        }
    }
}
