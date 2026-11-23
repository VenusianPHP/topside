<?php

namespace Venusian\Topside\Tests\Fakes;

use Surface\Toolkits\ToolkitAdapterServiceProvider;

class FakeProvider extends ToolkitAdapterServiceProvider
{
    protected string $toolkit = 'fake';

    protected string $driver = FakeDriver::class;

    protected string $config = __DIR__.'/../Fakes/config/fake.php';
}
