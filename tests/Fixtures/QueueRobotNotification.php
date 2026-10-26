<?php

namespace Fayne\LaravelNotification\Tests\Fixtures;

use Illuminate\Notifications\Notification as BaseNotification;

class QueueRobotNotification extends BaseNotification
{
    public function __construct(public string $message) {}

    public function via(object $notifiable): array
    {
        return ['wecom'];
    }

    public function toWecom(object $notifiable): string
    {
        return $this->message;
    }
}
