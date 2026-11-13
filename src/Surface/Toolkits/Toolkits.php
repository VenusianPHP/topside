<?php

namespace Surface\Toolkits;

use ReflectionException;
use Surface\Contracts\Toolkits\ToolkitBridge;
use Surface\Toolkits\Managers\ToolkitBridgeManager;
use Surface\Toolkits\Managers\ToolkitWindowManager;

class Toolkits
{
    /**
     * @throws ReflectionException
     */
    public static function bridge(?string $name = null): ToolkitBridge
    {
        /** @var ToolkitBridgeManager $mgr */
        $mgr = app('toolkit-bridge-manager');

        return $mgr->driver($name);
    }

    /**
     * @throws ReflectionException
     */
    public static function bridges(): array
    {
        /** @var ToolkitBridgeManager $mgr */
        $mgr = app('toolkit-bridge-manager');

        return $mgr->getDrivers();
    }

    /**
     * @throws ReflectionException
     */
    public static function window(): ToolkitWindowManager
    {
        /** @var ToolkitWindowManager */
        return app('toolkit-windows');
    }
}