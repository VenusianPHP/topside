<?php

namespace Venusian\Topside\Tests\Fakes;

use RuntimeException;
use Surface\Toolkits\Drivers\ToolkitAdapterDriver;
use Voyager\Contracts\Core\FrameworkCore;

/**
 * A toolkit whose extension or OS is missing: building its driver fails.
 */
class BrokenDriver extends ToolkitAdapterDriver
{
    public function __construct(FrameworkCore $app)
    {
        throw new RuntimeException('ext-broken missing.');
    }

    protected function instanceSession(): void {}
}
