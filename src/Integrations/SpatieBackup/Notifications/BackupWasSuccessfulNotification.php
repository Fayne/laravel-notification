<?php

namespace Fayne\LaravelNotification\Integrations\SpatieBackup\Notifications;

use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification as BaseNotification;

class BackupWasSuccessfulNotification extends BaseNotification
{
    use FormatsRobotMessage;

    protected const ROBOT_SUBJECT = 'backup_successful_subject';

    protected const ROBOT_LEVEL = 'info';
}
