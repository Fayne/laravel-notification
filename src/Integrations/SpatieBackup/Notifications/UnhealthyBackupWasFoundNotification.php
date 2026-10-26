<?php

namespace Fayne\LaravelNotification\Integrations\SpatieBackup\Notifications;

use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification as BaseNotification;

class UnhealthyBackupWasFoundNotification extends BaseNotification
{
    use FormatsRobotMessage;

    protected const ROBOT_SUBJECT = 'unhealthy_backup_found_subject';

    protected const ROBOT_LEVEL = 'error';
}
