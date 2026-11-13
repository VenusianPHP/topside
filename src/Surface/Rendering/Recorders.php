<?php

namespace Surface\Rendering;

use ReflectionException;
use Surface\Rendering\Recording\RecordingDriver;
use Surface\Rendering\Recording\RecordingLibraryManager;

/** The recording drivers: Recorders::driver()->recorder(). */
class Recorders
{
    /**
     * @throws ReflectionException
     */
    public static function driver(?string $name = null): RecordingDriver
    {
        return static::manager()->driver($name);
    }

    /**
     * @throws ReflectionException
     */
    public static function manager(): RecordingLibraryManager
    {
        return app('recorders');
    }
}
