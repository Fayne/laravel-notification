<?php

namespace Fayne\LaravelNotification\Integrations\SpatieBackup\Notifications;

use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification as BaseNotification;

class CleanupWasSuccessfulNotification extends BaseNotification
{
    use FormatsRobotMessage;

    protected const ROBOT_SUBJECT = 'cleanup_successful_subject';

    protected const ROBOT_LEVEL = 'info';
}
