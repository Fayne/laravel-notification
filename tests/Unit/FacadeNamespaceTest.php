<?php

namespace Fayne\LaravelNotification\Tests\Unit;

use Fayne\LaravelNotification\Facades\Dingding;
use Fayne\LaravelNotification\Tests\TestCase;

class FacadeNamespaceTest extends TestCase
{
    public function test_dingding_facade_accessor_is_correct(): void
    {
        $this->assertSame('dingding', Dingding::getFacadeAccessor());
    }
}
