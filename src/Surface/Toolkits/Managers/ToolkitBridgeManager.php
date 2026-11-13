<?php

namespace Surface\Toolkits\Managers;

use ReflectionException;
use Voyager\NutsAndBolts\Manager;

class ToolkitBridgeManager extends Manager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): string
    {
        $os = device_os_family();

        return config(
            "toolkits.bridge.{$os}.default",
            $os == 'linux' ? 'gtk' : 'appkit'
        );
    }
}