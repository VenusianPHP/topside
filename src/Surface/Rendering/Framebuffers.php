<?php

namespace Surface\Rendering;

use ReflectionException;
use Surface\Rendering\Framebuffers\FramebufferDriver;
use Surface\Rendering\Framebuffers\FramebufferLibraryManager;

class Framebuffers
{
    /**
     * @throws ReflectionException
     */
    public static function driver(?string $name = null): FramebufferDriver
    {
        return static::manager()->driver($name);
    }

    /**
     * @throws ReflectionException
     */
    public static function manager(): FramebufferLibraryManager
    {
        return app('framebuffers');
    }
}