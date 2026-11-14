<?php

namespace Surface\Graphics;

use Closure;
use ReflectionException;
use Surface\Contracts\Graphics\Backend;

/**
 * The way in to the graphics engines:
 *
 *     $engine = Gfx::engine('velvet', 'cpu');
 *     $frame = $engine->framebuffer(640, 480);
 *     $engine->painter($frame)->frame(fn ($g) => $g->fillRect(10, 10, 50, 50, $red));
 */
class Gfx
{
    /**
     * @param string|null $name null: graphics.engine.default
     * @param string|null $backend 'cpu', 'gpu' or 'auto'; null: graphics.engine.backend
     *
     * @throws ReflectionException
     */
    public static function engine(?string $name = null, ?string $backend = null): GraphicsEngine
    {
        return static::manager()->engine($name, $backend);
    }

    /**
     * Registers an engine, with the backends it offers.
     *
     * @throws ReflectionException
     */
    public static function extend(string $name, Closure $creator, Backend ...$backends): void
    {
        static::manager()->extend($name, $creator, ...$backends);
    }

    /**
     * @throws ReflectionException
     */
    public static function manager(): GraphicsEngineDeviceManager
    {
        return app('gfx');
    }
}
