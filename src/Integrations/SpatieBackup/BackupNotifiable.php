<?php

namespace Fayne\LaravelNotification\Integrations\SpatieBackup;

use Spatie\Backup\Notifications\Notifiable;

class BackupNotifiable extends Notifiable
{
    public function routeNotificationForDingding(): ?array
    {
        $webhook = config('backup.notifications.dingding.webhook');
        if (empty($webhook)) {
            return null;
        }

        return [
            'webhook' => $webhook,
            'secret' => (string) config('backup.notifications.dingding.secret', ''),
        ];
    }

    public function routeNotificationForWecom(): ?string
    {
        return config('backup.notifications.wecom.robot_key');
    }
}
