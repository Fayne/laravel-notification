<?php

namespace MobileNowGroup\Notification\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static send()void
 * @method static push()void
 */
class Dingding extends Facade
{
    /**
     * Return the facade accessor.
     *
     * @return string
     */
    public static function getFacadeAccessor()
    {
        return 'dingding';
    }
}
