<?php

namespace Fayne\LaravelNotification\Channels;

use Fayne\LaravelNotification\Services\SendDingdingService;
use Illuminate\Notifications\Notification;

class DingdingChannel
{
    public function __construct(private SendDingdingService $service) {}

    public function send(object $notifiable, Notification $notification): ?array
    {
        $route = $notifiable->routeNotificationFor('dingding', $notification);
        if (empty($route)) {
            return null;
        }

        $message = $notification->toDingding($notifiable);
        if (is_string($message)) {
            $message = ['msgtype' => 'text', 'text' => ['content' => $message]];
        }

        return $this->service->push($message, $route, false);
    }
}
