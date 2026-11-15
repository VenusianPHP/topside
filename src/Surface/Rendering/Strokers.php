<?php

namespace Surface\Rendering;

use ReflectionException;
use Surface\Rendering\Stroking\StrokingDriver;
use Surface\Rendering\Stroking\StrokingLibraryManager;

/** The stroking drivers: Strokers::driver()->stroker(). */
class Strokers
{
    /**
     * @throws ReflectionException
     */
    public static function driver(?string $name = null): StrokingDriver
    {
        return static::manager()->driver($name);
    }

    /**
     * @throws ReflectionException
     */
    public static function manager(): StrokingLibraryManager
    {
        return app('strokers');
    }
}
