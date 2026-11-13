<?php

namespace Surface\Rendering;

use ReflectionException;
use Surface\Rendering\Rasterization\RasterizationDriver;
use Surface\Rendering\Rasterization\RasterizationLibraryManager;

class Rasterization
{
    /**
     * @throws ReflectionException
     */
    public static function driver(?string $name = null): RasterizationDriver
    {
        return static::manager()->driver($name);
    }

    /**
     * @throws ReflectionException
     */
    public static function manager(): RasterizationLibraryManager
    {
        return app('rasterize');
    }
}