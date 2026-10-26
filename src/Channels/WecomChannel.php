<?php

namespace Fayne\LaravelNotification\Channels;

use Fayne\LaravelNotification\Services\AppChatSendService;
use Illuminate\Notifications\Notification;

class WecomChannel
{
    public function __construct(private AppChatSendService $service) {}

    public function send(object $notifiable, Notification $notification): ?array
    {
        $route = $notifiable->routeNotificationFor('wecom', $notification);
        if (empty($route)) {
            return null;
        }

        $message = $notification->toWecom($notifiable);
        if (is_string($message)) {
            $message = ['msgtype' => 'text', 'text' => ['content' => $message]];
        }

        return $this->service->push(['robot_key' => $route, 'message' => $message], false);
    }
}
