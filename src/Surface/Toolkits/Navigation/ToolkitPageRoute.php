<?php

namespace Surface\Toolkits\Navigation;

use InvalidArgumentException;

class ToolkitPageRoute
{
    /** @var array<string, array{0: class-string, 1: string}> */
    protected static array $routes = [];

    public static function get(string $name, array $action): void
    {
        static::$routes[$name] = $action;
    }

    public static function find(string $name): array
    {
        return static::$routes[$name] ?? throw new InvalidArgumentException("No route named [{$name}].");
    }
}