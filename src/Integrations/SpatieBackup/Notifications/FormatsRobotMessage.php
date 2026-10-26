<?php

namespace Fayne\LaravelNotification\Integrations\SpatieBackup\Notifications;

trait FormatsRobotMessage
{
    public function toDingding(object $notifiable): string
    {
        return $this->robotMessage();
    }

    public function toWecom(object $notifiable): string
    {
        return $this->robotMessage();
    }

    private function robotMessage(): string
    {
        $lines = [
            '['.static::ROBOT_LEVEL.'] '.trans('backup::notifications.'.static::ROBOT_SUBJECT, [
                'application_name' => $this->applicationName(),
                'disk_name' => $this->event->diskName ?? '-',
            ]),
            trans('backup::notifications.backup_name').': '.($this->event->backupName ?? '-'),
            trans('backup::notifications.disk').': '.($this->event->diskName ?? '-'),
        ];

        if (isset($this->event->failureMessages)) {
            $lines[] = 'Checks: '.$this->event->failureMessages
                ->map(fn (array $failure) => class_basename($failure['check']))->implode(', ');
        }

        if (static::ROBOT_LEVEL === 'error') {
            $lines[] = 'Details: see application logs.';
        }

        // Leave room below the WeCom text limit without splitting UTF-8 characters.
        return mb_strcut(implode("\n", $lines), 0, 1900, 'UTF-8');
    }
}
