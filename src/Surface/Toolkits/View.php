<?php

namespace Surface\Toolkits;

use ReflectionException;
use Surface\Toolkits\Drivers\ContentTreeDriver;
use Surface\Toolkits\Managers\ContentTreeManager;

class View
{
    /**
     * @throws ReflectionException
     */
    public static function driver(?string $name = null): ContentTreeDriver
    {
        return app(ContentTreeManager::class)->driver($name);
    }

    /**
     * @throws ReflectionException
     */
    public static function render(string $route, array $args = []): void
    {
        app(ContentTreeManager::class)->render($route, $args);
    }
}