<?php

namespace Surface\Rendering;

use ReflectionException;
use Surface\Rendering\Painters\PaintingDriver;
use Surface\Rendering\Painters\PaintingLibraryManager;

/** The painting drivers: Painters::driver()->painter($framebuffer). */
class Painters
{
    /**
     * @throws ReflectionException
     */
    public static function driver(?string $name = null): PaintingDriver
    {
        return static::manager()->driver($name);
    }

    /**
     * @throws ReflectionException
     */
    public static function manager(): PaintingLibraryManager
    {
        return app('painters');
    }
}
