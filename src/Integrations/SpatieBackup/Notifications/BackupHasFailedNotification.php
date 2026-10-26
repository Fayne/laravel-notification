<?php

namespace Fayne\LaravelNotification\Integrations\SpatieBackup\Notifications;

use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification as BaseNotification;

class BackupHasFailedNotification extends BaseNotification
{
    use FormatsRobotMessage;

    protected const ROBOT_SUBJECT = 'backup_failed_subject';

    protected const ROBOT_LEVEL = 'error';
}
