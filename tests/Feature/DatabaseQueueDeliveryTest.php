<?php

namespace Fayne\LaravelNotification\Tests\Feature;

use Fayne\LaravelNotification\Exceptions\RobotDeliveryException;
use Fayne\LaravelNotification\Services\AppChatSendService;
use Fayne\LaravelNotification\Services\SendDingdingService;
use Fayne\LaravelNotification\Tests\Fixtures\AsyncQueueRobotNotification;
use Fayne\LaravelNotification\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class DatabaseQueueDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Http::preventStrayRequests();
        config([
            'database.connections.robot_tests' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'queue.default' => 'robot_tests',
            'queue.connections.robot_tests' => ['driver' => 'database', 'connection' => 'robot_tests', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90],
        ]);
        Schema::connection('robot_tests')->create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function test_real_queue_retries_business_failure_then_completes_delivery(): void
    {
        Http::fake(['*' => Http::sequence()->push(['errcode' => 1])->push(['errcode' => 0])]);
        (new SendDingdingService)->send('retry me');
        $this->processNext(true);
        $this->assertSame(1, Queue::size());
        $this->travel(11)->seconds();
        $this->processNext();
        $this->assertSame(0, Queue::size());
        Http::assertSentCount(2);
    }

    public function test_real_queue_stops_after_exception_budget_is_exhausted(): void
    {
        config(['dingding.retry_times' => 2]);
        Http::fake(['*' => Http::response(['errcode' => 1])]);
        Event::fake([JobFailed::class]);
        (new SendDingdingService)->send('always fails');
        $this->processNext(true);
        $this->travel(11)->seconds();
        $this->processNext(true);
        $this->assertSame(0, Queue::size());
        Event::assertDispatchedTimes(JobFailed::class, 1);
        Http::assertSentCount(2);
    }

    public function test_real_queue_releases_throttled_legacy_job_and_later_sends_it(): void
    {
        config(['wechat.work.robot_alarm.count' => 1]);
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        $service = new AppChatSendService;
        $service->send('first');
        $service->send('second');
        $this->processNext();
        $this->processNext();
        $this->assertSame(1, Queue::size());
        Http::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->processNext();
        $this->assertSame(0, Queue::size());
        Http::assertSentCount(2);
    }

    public function test_real_queue_releases_native_notification_and_later_sends_it(): void
    {
        config(['wechat.work.robot_alarm.count' => 1]);
        Http::fake(['*' => Http::response(['errcode' => 0])]);
        Notification::route('wecom', 'native-key')->notify(new RetryingNativeNotification('first'));
        Notification::route('wecom', 'native-key')->notify(new RetryingNativeNotification('second'));
        $this->processNext();
        $this->processNext();
        $this->assertSame(1, Queue::size());
        Http::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->processNext();
        $this->assertSame(0, Queue::size());
        Http::assertSentCount(2);
    }

    private function processNext(bool $expectFailure = false): void
    {
        $job = Queue::pop();
        $this->assertNotNull($job);
        $caught = false;
        try {
            $worker = app('queue.worker');
            $worker->setCache(app('cache')->store());
            $worker->process('robot_tests', $job, new WorkerOptions(maxTries: 1));
        } catch (RobotDeliveryException) {
            $caught = true;
        }
        $this->assertSame($expectFailure, $caught);
    }
}

class RetryingNativeNotification extends AsyncQueueRobotNotification
{
    public int $maxExceptions = 3;

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(10);
    }
}
