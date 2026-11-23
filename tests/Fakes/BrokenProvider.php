<?php

namespace Venusian\Topside\Tests\Fakes;

use Surface\Toolkits\ToolkitAdapterServiceProvider;

class BrokenProvider extends ToolkitAdapterServiceProvider
{
    protected string $toolkit = 'broken';

    protected string $driver = BrokenDriver::class;

    protected string $config = __DIR__.'/config/fake.php';
}
