<?php

namespace Fayne\LaravelNotification\Exceptions;

class RobotRateLimitedException extends RobotDeliveryException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Robot delivery is busy or rate limited; retry later.');
    }
}
