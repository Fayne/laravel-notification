<?php

namespace MobileNowGroup\LaravelNotification\Tests\Unit;

use MobileNowGroup\LaravelNotification\Facades\Dingding;
use MobileNowGroup\LaravelNotification\Tests\TestCase;

class FacadeNamespaceTest extends TestCase
{
    public function test_dingding_facade_accessor_is_correct(): void
    {
        $this->assertSame('dingding', Dingding::getFacadeAccessor());
    }
}
