<?php

namespace Fayne\LaravelNotification\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static send()void
 * @method static push()void
 */
class Dingding extends Facade
{
    /**
     * Return the facade accessor.
     */
    public static function getFacadeAccessor(): string
    {
        return 'dingding';
    }
}
