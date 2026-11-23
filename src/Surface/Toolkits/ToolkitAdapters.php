<?php

namespace Surface\Toolkits;

use ReflectionException;
use Surface\Contracts\Toolkits\ToolkitAdapterDriver;
use Surface\Toolkits\Managers\ToolkitAdapterManager;
use Surface\Toolkits\Managers\ToolkitWindowManager;

class ToolkitAdapters
{
    /**
     * @throws ReflectionException
     */
    public static function extend(string $name, callable $action): void
    {
        /** @var ToolkitAdapterManager $mgr */
        $mgr = app('toolkit-adapters');
        $mgr->extend($name, $action);
    }

    /**
     * @throws ReflectionException
     */
    public static function driver(?string $name = null): ToolkitAdapterDriver
    {
        /** @var ToolkitAdapterManager $mgr */
        $mgr = app('toolkit-adapters');
        return $mgr->driver($name);
    }

    /**
     * @throws ReflectionException
     */
    public static function adapters(): array
    {
        /** @var ToolkitAdapterManager $mgr */
        $mgr = app('toolkit-adapters');
        return $mgr->getDrivers();
    }

    public static function windows(): ToolkitWindowManager
    {
        return app('toolkit-windows');
    }
}