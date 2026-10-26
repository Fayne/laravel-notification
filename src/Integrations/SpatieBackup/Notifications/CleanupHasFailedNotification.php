<?php

namespace Fayne\LaravelNotification\Integrations\SpatieBackup\Notifications;

use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification as BaseNotification;

class CleanupHasFailedNotification extends BaseNotification
{
    use FormatsRobotMessage;

    protected const ROBOT_SUBJECT = 'cleanup_failed_subject';

    protected const ROBOT_LEVEL = 'error';
}
