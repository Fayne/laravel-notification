<?php

namespace MobileNowGroup\LaravelNotification\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static send()void
 * @method static push()void
 */
class AppChatSend extends Facade
{
    /**
     * Return the facade accessor.
     */
    public static function getFacadeAccessor(): string
    {
        return 'app-chat-send';
    }
}
