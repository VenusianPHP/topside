<?php

namespace Surface\Toolkits;

use ReflectionException;
use Surface\Toolkits\Drivers\PresentationTreeDriver;
use Surface\Toolkits\Managers\PresentationTreeManager;

class Presentation
{
    /**
     * @throws ReflectionException
     */
    public static function driver(?string $name = null): PresentationTreeDriver
    {
        return app(PresentationTreeManager::class)->driver($name);
    }
}