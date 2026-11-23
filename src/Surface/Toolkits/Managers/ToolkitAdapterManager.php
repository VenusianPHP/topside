<?php

namespace Surface\Toolkits\Managers;

use ReflectionException;
use Surface\Toolkits\ToolkitException;
use Voyager\NutsAndBolts\Manager;

class ToolkitAdapterManager extends Manager
{
    public function createNullDriver(): never
    {
        throw new ToolkitException("No default toolkit set.");
    }

    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return config('toolkits.default','null');
    }
}